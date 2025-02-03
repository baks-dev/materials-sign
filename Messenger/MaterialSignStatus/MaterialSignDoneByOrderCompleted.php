<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Materials\Sign\Messenger\MaterialSignStatus;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Materials\Catalog\Repository\MaterialModificationConst\MaterialModificationConstInterface;
use BaksDev\Materials\Catalog\Repository\MaterialOfferConst\MaterialOfferConstInterface;
use BaksDev\Materials\Catalog\Repository\MaterialVariationConst\MaterialVariationConstInterface;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\MaterialSignProcessByOrder\MaterialSignProcessByOrderInterface;
use BaksDev\Materials\Sign\Repository\MaterialSignProcessByOrderMaterial\MaterialSignProcessByOrderProductInterface;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignCancelDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignDoneDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\OrderStatusCompleted;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MaterialSignDoneByOrderCompleted
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MaterialOfferConstInterface $materialOfferConst,
        private MaterialVariationConstInterface $materialVariationConst,
        private MaterialModificationConstInterface $materialModificationConst,
        private MaterialSignStatusHandler $materialSignStatusHandler,
        private OrderEventInterface $orderEventRepository,
        private MaterialSignProcessByOrderProductInterface $materialSignProcessByOrderMaterial,
        private MaterialSignProcessByOrderInterface $materialSignProcessByOrder,
        private DeduplicatorInterface $deduplicator,
    ) {}


    /**
     * Делаем отметку Честный знак Done «Выполнен» если статус заказа Completed «Выполнен»
     */
    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('materials-sign')
            ->deduplication([
                (string) $message->getId(),
                MaterialSignStatusDone::STATUS,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        /** Log Data */
        $dataLogs['OrderUid'] = (string) $message->getId();
        $dataLogs['OrderEventUid'] = (string) $message->getEvent();
        $dataLogs['LastOrderEventUid'] = (string) $message->getLast();

        /** Получаем событие заказа */
        $OrderEvent = $this->orderEventRepository->find($message->getEvent());

        if(false === $OrderEvent)
        {
            $dataLogs[0] = self::class.':'.__LINE__;
            $this->logger->critical('materials-sign: Не найдено событие Order', $dataLogs);

            return;
        }

        /**
         * Если статус не Completed «Выполнен» - завершаем обработчик
         */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCompleted::class))
        {
            return;
        }

        $this->logger->info('Делаем отметку «Честный знак» Done «Выполнен»');

        /** @var OrderProduct $product */
        foreach($OrderEvent->getProduct() as $product)
        {
            /**
             * Получаем константы сырья по идентификаторам
             */
            $MaterialOfferUid = $product->getOffer() ? $this->materialOfferConst->getConst($product->getOffer()) : null;
            $MaterialVariationUid = $product->getVariation() ? $this->materialVariationConst->getConst($product->getVariation()) : null;
            $MaterialModificationUid = $product->getModification() ? $this->materialModificationConst->getConst($product->getModification()) : null;

            /**
             * Чекаем честный знак о выполнении
             */
            $total = $product->getTotal();

            for($i = 1; $i <= $total; $i++)
            {
                $MaterialSignEvent = $this->materialSignProcessByOrderMaterial
                    ->forOrder($message->getId())
                    ->forOfferConst($MaterialOfferUid)
                    ->forVariationConst($MaterialVariationUid)
                    ->forModificationConst($MaterialModificationUid)
                    ->find();

                if($MaterialSignEvent)
                {
                    $MaterialSignDoneDTO = new MaterialSignDoneDTO();
                    $MaterialSignEvent->getDto($MaterialSignDoneDTO);

                    $handle = $this->materialSignStatusHandler->handle($MaterialSignDoneDTO);

                    if(!$handle instanceof MaterialSign)
                    {
                        $this->logger->critical(
                            sprintf('%s: Ошибка при обновлении статуса честного знака', $handle),
                            [
                                self::class.':'.__LINE__,
                                'MaterialSignEventUid' => $MaterialSignDoneDTO->getEvent()
                            ]
                        );

                        throw new InvalidArgumentException('Ошибка при обновлении статуса честного знака');
                    }

                    $this->logger->info(
                        'Отметили Честный знак Done «Выполнен»',
                        [
                            self::class.':'.__LINE__,
                            'MaterialSignUid' => $MaterialSignEvent->getMain()
                        ]
                    );
                }
            }
        }

        /**
         * Если по заказу остались Честный знак в статусе Process «В процессе» - делаем отмену (присваиваем статус New «Новый»)
         * @note ситуация если количество в заказе изменилось на меньшее количество
         */

        $MaterialSignEvents = $this->materialSignProcessByOrder->findByOrder($message->getId());

        foreach($MaterialSignEvents as $event)
        {
            $MaterialSignCancelDTO = new MaterialSignCancelDTO($event->getProfile());
            $event->getDto($MaterialSignCancelDTO);
            $this->materialSignStatusHandler->handle($MaterialSignCancelDTO);

            $this->logger->warning(
                'Отменили Честный знак (возвращаем статус New «Новый»)',
                [
                    self::class.':'.__LINE__,
                    'MaterialSignUid' => $event->getMain()
                ]
            );
        }

    }
}

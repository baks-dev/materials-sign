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
use BaksDev\Materials\Catalog\Repository\CurrentMaterialIdentifier\CurrentIdentifierMaterialByValueInterface;
use BaksDev\Materials\Catalog\Repository\CurrentMaterialIdentifier\CurrentMaterialDTO;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\MaterialSignProcessByOrderMaterial\MaterialSignProcessByOrderProductInterface;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignDoneDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCompleted;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierInterface;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierResult;
use BaksDev\Products\Product\Repository\ProductMaterials\ProductMaterialsInterface;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Делаем отметку Честный знак на сырье Done «Выполнен» если статус заказа Completed «Выполнен»
 */
#[AsMessageHandler(priority: 10)]
final readonly class MaterialSignDoneByOrderCompletedDispatcher
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MaterialSignStatusHandler $materialSignStatusHandler,
        private OrderEventInterface $OrderEventRepository,
        private MaterialSignProcessByOrderProductInterface $materialSignProcessByOrderMaterial,
        private ProductMaterialsInterface $ProductMaterials,
        private CurrentProductIdentifierInterface $CurrentProductIdentifier,
        private CurrentIdentifierMaterialByValueInterface $CurrentIdentifierMaterialByValue,
        private DeduplicatorInterface $deduplicator,
    ) {}

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

        /** Получаем событие заказа */
        $OrderEvent = $this->OrderEventRepository
            ->find($message->getEvent());

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            $this->logger->critical('materials-sign: Не найдено событие Order');
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
            /** Получаем идентификаторы продукции */
            $CurrentProductIdentifier = $this->CurrentProductIdentifier
                ->forEvent($product->getProduct())
                ->forOffer($product->getOffer())
                ->forVariation($product->getVariation())
                ->forModification($product->getModification())
                ->find();

            if(false === ($CurrentProductIdentifier instanceof CurrentProductIdentifierResult))
            {
                continue;
            }

            /** Получаем список материалов продукции */

            $ProductMaterials = $this->ProductMaterials
                ->forEvent($CurrentProductIdentifier->getEvent())
                ->findAll();

            if(false === ($ProductMaterials || $ProductMaterials->valid()))
            {
                continue;
            }

            /** @var MaterialUid $ProductMaterial */
            foreach($ProductMaterials as $ProductMaterial)
            {
                /** Получаем материал согласно торговому предложению (value) */
                $CurrentMaterialDTO = $this->CurrentIdentifierMaterialByValue
                    ->forMaterial($ProductMaterial)
                    ->forOfferValue($CurrentProductIdentifier->getOfferValue())
                    ->forVariationValue($CurrentProductIdentifier->getVariationValue())
                    ->forModificationValue($CurrentProductIdentifier->getModificationValue())
                    ->find();

                if(false === ($CurrentMaterialDTO instanceof CurrentMaterialDTO))
                {
                    continue;
                }

                $total = $product->getTotal();

                for($i = 1; $i <= $total; $i++)
                {
                    $MaterialSignEvent = $this->materialSignProcessByOrderMaterial
                        ->forOrder($message->getId())
                        ->forOfferConst($CurrentMaterialDTO->getOfferConst())
                        ->forVariationConst($CurrentMaterialDTO->getVariationConst())
                        ->forModificationConst($CurrentMaterialDTO->getModificationConst())
                        ->find();

                    if(false === ($MaterialSignEvent instanceof MaterialSignEvent))
                    {
                        $this->logger->warning(
                            'Честный знак на сырьё не найден',
                            [$message, $product, self::class.':'.__LINE__]
                        );

                        break;
                    }

                    /**
                     * Обновляем «Честный знак» на статус Done «Выполнен»
                     */

                    $MaterialSignDoneDTO = new MaterialSignDoneDTO();
                    $MaterialSignEvent->getDto($MaterialSignDoneDTO);

                    $MaterialSign = $this->materialSignStatusHandler->handle($MaterialSignDoneDTO);

                    if(false === ($MaterialSign instanceof MaterialSign))
                    {
                        $this->logger->critical(
                            sprintf('%s: Ошибка при обновлении статуса честного знака', $MaterialSign),
                            [
                                self::class.':'.__LINE__,
                                'MaterialSignEventUid' => $MaterialSignDoneDTO->getEvent()
                            ]
                        );

                        break;
                    }

                    $this->logger->info(
                        'Отметили Честный знак на сырье в статус Done «Выполнен»',
                        [$message, self::class.':'.__LINE__,]
                    );
                }
            }

            /**
             * Все остальные незавершенные «Честные знаки» будут отменены при вызове MaterialSignCancelByOrderCanceled
             * @see MaterialSignCancelByOrderCanceledDispatcher
             */

            $Deduplicator->save();

        }
    }
}

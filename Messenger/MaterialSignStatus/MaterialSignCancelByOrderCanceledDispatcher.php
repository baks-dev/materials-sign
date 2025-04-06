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
use BaksDev\Materials\Sign\Repository\MaterialSignProcessByOrder\MaterialSignProcessByOrderInterface;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusCancel;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignCancelDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCanceled;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Делаем отмену Честный знак на сырье New «Новый» если статус заказа Canceled «Отменен»
 */
#[AsMessageHandler(priority: 80)]
final readonly class MaterialSignCancelByOrderCanceledDispatcher
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MaterialSignStatusHandler $materialSignStatusHandler,
        private OrderEventInterface $OrderEventRepository,
        private MaterialSignProcessByOrderInterface $materialSignProcessByOrder,
        private DeduplicatorInterface $deduplicator,
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('materials-sign')
            ->deduplication([
                (string) $message->getId(),
                MaterialSignStatusCancel::STATUS,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $OrderEvent = $this->OrderEventRepository
            ->find($message->getEvent());

        if(false === $OrderEvent)
        {
            $this->logger->critical(
                'materials-sign: Не найдено событие Order',
                [self::class.':'.__LINE__, var_export($message, true)]
            );

            return;
        }

        /**
         * Если статус не Canceled «Отмена» - завершаем обработчик
         */
        if(false === $OrderEvent->isStatusEquals(OrderStatusCanceled::class))
        {
            return;
        }

        /**
         * Делаем поиск и отмену всех «Честных знаков» и возвращаем в реализацию при условии:
         * - при отмене заказа
         * - при завершении заказа, но если найдены незакрытые ЧЗ (
         * например если изменилось количество в заказе @see MaterialSignDoneByOrderCompletedDispatcher у которого выше приоритетом
         */

        $this->logger->info('Делаем поиск и отмену «Честных знаков»:');

        $events = $this->materialSignProcessByOrder
            ->forOrder($message->getId())
            ->findAllByOrder();

        foreach($events as $MaterialSignEvent)
        {
            $MaterialSignCancelDTO = new MaterialSignCancelDTO();
            $MaterialSignEvent->getDto($MaterialSignCancelDTO);
            $this->materialSignStatusHandler->handle($MaterialSignCancelDTO);

            $this->logger->warning(
                'Отменили «Честный знак» (возвращаем статус New «Новый»)',
                [
                    self::class.':'.__LINE__,
                    'MaterialSignUid' => $MaterialSignEvent->getMain()
                ]
            );
        }

        $Deduplicator->save();
    }
}

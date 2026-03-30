<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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
 *
 */

declare(strict_types=1);

namespace BaksDev\Materials\Sign\Messenger\MaterialSignStatus\MaterialSignProcess;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Messenger\MaterialSignMatrixCode\MaterialSignMatrixCodeMessage;
use BaksDev\Materials\Sign\Repository\MaterialSignNew\MaterialSignNewInterface;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignProcessDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Ставит в резерв честный знак по заказу */
#[AsMessageHandler(priority: 0)]
#[Autoconfigure(shared: false)]
final readonly class MaterialSignProcessDispatcher
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MaterialSignStatusHandler $MaterialSignStatusHandler,
        private MaterialSignNewInterface $MaterialSignNewRepository,
        private MessageDispatchInterface $MessageDispatch,
    ) {}

    public function __invoke(MaterialSignProcessMessage $message): void
    {
        $materialSignEvent = $this->MaterialSignNewRepository
            ->forUser($message->getUser())
            ->forProfile($message->getProfile())
            ->forMaterial($message->getMaterial())
            ->forOfferConst($message->getOffer())
            ->forVariationConst($message->getVariation())
            ->forModificationConst($message->getModification())
            ->getOneMaterialSign();


        if(false === ($materialSignEvent instanceof MaterialSignEvent))
        {
            $this->logger->warning(
                'Честный знак на продукцию не найден',
                [var_export($message, true), self::class.':'.__LINE__],
            );

            return;
        }

        if(false === $materialSignEvent->isInvariable())
        {
            $this->MessageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('1 seconds')],
                transport: 'materials-sign',
            );

            return;
        }

        /**
         * Резервируем «Честный знак»
         */

        $materialSignProcessDTO = new MaterialSignProcessDTO($message->getProfile(), $message->getOrder());
        $materialSignEvent->getDto($materialSignProcessDTO);

        /** Присваиваем партию упаковки */
        $materialSignProcessDTO->getInvariable()
            ->setSeller($message->getProfile())
            ->setPart($message->getPart());

        $MaterialSign = $this->MaterialSignStatusHandler->handle($materialSignProcessDTO);

        if(false === ($MaterialSign instanceof MaterialSign))
        {
            $this->logger->critical(
                sprintf('%s: Ошибка при обновлении статуса честного знака', $MaterialSign),
                [var_export($message, true), self::class.':'.__LINE__],
            );

            $this->MessageDispatch->dispatch(
                message: $message,
                stamps: [new MessageDelay('5 seconds')],
                transport: 'materials-sign',
            );

            return;
        }

        $this->logger->info(
            'Отметили Честный знак Process «В резерве»',
            [var_export($message, true), self::class.':'.__LINE__],
        );

        /** Прогреваем кеш стикеров честных знаков заказа */
        $MaterialSignMatrixCodeMessage = new MaterialSignMatrixCodeMessage($MaterialSign);

        $this->MessageDispatch->dispatch(
            message: $MaterialSignMatrixCodeMessage,
            transport: 'files-res',
        );
    }
}

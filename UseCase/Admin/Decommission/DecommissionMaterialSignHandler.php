<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Materials\Sign\UseCase\Admin\Decommission;

use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\MaterialSignNew\MaterialSignNewInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignDecommissionDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;

final class DecommissionMaterialSignHandler
{
    public function __construct(
        private readonly MaterialSignNewInterface $materialSignNew,
        private readonly MaterialSignStatusHandler $materialSignStatusHandler,
    ) {}

    /** @see MaterialSign */
    public function handle(DecommissionMaterialSignDTO $command): string|MaterialSignUid
    {
        $MaterialSignUid = new MaterialSignUid();

        /** Получаем свободный честный знак для списания */
        for($i = 1; $i <= $command->getTotal(); $i++)
        {
            $MaterialSignEvent = $this->materialSignNew
                ->forUser($command->getUsr())
                ->forProfile($command->getProfile())
                ->forMaterial($command->getMaterial())
                ->forOfferConst($command->getOffer())
                ->forVariationConst($command->getVariation())
                ->forModificationConst($command->getModification())
                ->getOneMaterialSign();

            if($MaterialSignEvent === false)
            {
                return 'Недостаточное количество честных знаков';
            }

            /** Меняем статус и присваиваем идентификатор партии  */
            $MaterialSignOffDTO = new MaterialSignDecommissionDTO();
            $MaterialSignInvariableDTO = $MaterialSignOffDTO->getInvariable();

            $MaterialSignInvariableDTO
                ->setSeller($command->getProfile())
                ->setPart($MaterialSignUid);

            $MaterialSignEvent->getDto($MaterialSignOffDTO);

            $handle = $this->materialSignStatusHandler->handle($MaterialSignOffDTO);

            if(false === ($handle instanceof MaterialSign))
            {
                return sprintf('%s: Ошибка при списании честных знаков', $handle);
            }
        }

        return $MaterialSignUid;
    }
}

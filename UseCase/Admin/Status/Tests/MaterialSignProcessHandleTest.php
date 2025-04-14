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

namespace BaksDev\Materials\Sign\UseCase\Admin\Status\Tests;

use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\CurrentEvent\MaterialSignCurrentEventInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\Collection\MaterialSignStatusCollection;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusProcess;
use BaksDev\Materials\Sign\UseCase\Admin\New\Tests\MaterialSignEditHandleTest;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignProcessDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Material\OrderMaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group materials-sign
 * @depends BaksDev\Materials\Sign\UseCase\Admin\New\Tests\MaterialSignEditHandleTest::class
 */
#[When(env: 'test')]
final class MaterialSignProcessHandleTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /**
         * Инициируем статус для итератора тегов
         * @var MaterialSignStatusCollection $status
         */
        $status = self::getContainer()->get(MaterialSignStatusCollection::class);
        $status->cases();

        /** @var MaterialSignCurrentEventInterface $MaterialSignCurrentEvent */
        $MaterialSignCurrentEvent = self::getContainer()->get(MaterialSignCurrentEventInterface::class);
        $MaterialSignEvent = $MaterialSignCurrentEvent
            ->forMaterialSign(MaterialSignUid::TEST)
            ->find();

        self::assertNotNull($MaterialSignEvent);


        /** @see MaterialSignCancelDTO */

        $MaterialSignDTO = new MaterialSignProcessDTO($UserProfileUid = clone new UserProfileUid(), $OrderUid = new OrderUid());
        $MaterialSignEvent->getDto($MaterialSignDTO);

        self::assertSame($OrderUid, $MaterialSignDTO->getOrd());
        self::assertTrue($MaterialSignDTO->getStatus()->equals(MaterialSignStatusProcess::class));
        self::assertSame($UserProfileUid, $MaterialSignDTO->getInvariable()->getSeller());

        /** @var MaterialSignStatusHandler $MaterialSignStatusHandler */
        $MaterialSignStatusHandler = self::getContainer()->get(MaterialSignStatusHandler::class);
        $handle = $MaterialSignStatusHandler->handle($MaterialSignDTO);

        self::assertTrue(($handle instanceof MaterialSign), $handle.': Ошибка MaterialSign');

    }
}
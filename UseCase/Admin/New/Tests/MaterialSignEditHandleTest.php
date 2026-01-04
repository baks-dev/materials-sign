<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Materials\Sign\UseCase\Admin\New\Tests;

use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\CurrentEvent\MaterialSignCurrentEventInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Materials\Sign\UseCase\Admin\New\Code\MaterialSignCodeDTO;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignDTO;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignHandler;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[When(env: 'test')]
#[Group('materials-sign')]
final class MaterialSignEditHandleTest extends KernelTestCase
{

    #[DependsOnClass(MaterialSignNewHandleTest::class)]
    public function testUseCase(): void
    {
        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var MaterialSignCurrentEventInterface $MaterialSignCurrentEvent */
        $MaterialSignCurrentEvent = self::getContainer()->get(MaterialSignCurrentEventInterface::class);
        $MaterialSignEvent = $MaterialSignCurrentEvent
            ->forMaterialSign(new MaterialSignUid(MaterialSignUid::TEST))
            ->find();

        self::assertNotNull($MaterialSignEvent);


        /** @see MaterialSignDTO */

        $MaterialSignDTO = new MaterialSignDTO();
        $MaterialSignEvent->getDto($MaterialSignDTO);

        //self::assertSame($UserProfileUid, $MaterialSignDTO->getProfile());

        self::assertTrue($MaterialSignDTO->getStatus()->equals(MaterialSignStatusNew::class));
        $MaterialSignDTO->setStatus(MaterialSignStatusDone::class);

        /** @see MaterialSignCodeDTO */

        $MaterialSignCodeDTO = $MaterialSignDTO->getCode();

        self::assertEquals('code', $MaterialSignCodeDTO->getCode());
        $MaterialSignCodeDTO->setCode('code_edit');

        //self::assertNotNull($MaterialSignCodeDTO->getQr());

        $MaterialSignInvariableDTO = $MaterialSignDTO->getInvariable();

        self::assertTrue($MaterialSignInvariableDTO->getMaterial()->equals(MaterialUid::TEST));
        $MaterialSignInvariableDTO->setMaterial(clone $MaterialSignInvariableDTO->getMaterial());

        self::assertTrue($MaterialSignInvariableDTO->getOffer()->equals(MaterialOfferConst::TEST));
        $MaterialSignInvariableDTO->setOffer(clone $MaterialSignInvariableDTO->getOffer());

        self::assertTrue($MaterialSignInvariableDTO->getVariation()->equals(MaterialVariationConst::TEST));
        $MaterialSignInvariableDTO->setVariation(clone $MaterialSignInvariableDTO->getVariation());

        self::assertTrue($MaterialSignInvariableDTO->getModification()->equals(MaterialModificationConst::TEST));
        $MaterialSignInvariableDTO->setModification(clone $MaterialSignInvariableDTO->getModification());


        /** @var MaterialSignHandler $MaterialSignHandler */
        $MaterialSignHandler = self::getContainer()->get(MaterialSignHandler::class);
        $handle = $MaterialSignHandler->handle($MaterialSignDTO);

        self::assertTrue(($handle instanceof MaterialSign), $handle.': Ошибка MaterialSign');

    }
}
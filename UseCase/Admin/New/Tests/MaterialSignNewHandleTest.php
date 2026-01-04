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

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Materials\Sign\UseCase\Admin\New\Code\MaterialSignCodeDTO;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignDTO;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignHandler;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
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
final class MaterialSignNewHandleTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
    {
        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $main = $em->getRepository(MaterialSign::class)
            ->find(MaterialSignUid::TEST);

        if($main)
        {
            $em->remove($main);
        }

        /* WbBarcodeEvent */

        $event = $em->getRepository(MaterialSignEvent::class)
            ->findBy(['main' => MaterialSignUid::TEST]);

        foreach($event as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();
        $em->clear();
    }


    public function testUseCase(): void
    {
        /** @see MaterialSignDTO */

        $MaterialSignDTO = new MaterialSignDTO();
        self::assertTrue($MaterialSignDTO->getStatus()->equals(MaterialSignStatusNew::class));


        /** @see MaterialSignCodeDTO */

        $MaterialSignCodeDTO = $MaterialSignDTO->getCode();

        $MaterialSignCodeDTO->setCode('code');
        self::assertEquals('code', $MaterialSignCodeDTO->getCode());

        $MaterialSignCodeDTO->setName('name');
        self::assertEquals('name', $MaterialSignCodeDTO->getName());

        $MaterialSignCodeDTO->setExt('webp');
        self::assertEquals('webp', $MaterialSignCodeDTO->getExt());


        $MaterialSignInvariableDTO = $MaterialSignDTO->getInvariable();

        $MaterialSignInvariableDTO->setUsr($UserUid = new UserUid(UserUid::TEST));
        self::assertSame($UserUid, $MaterialSignInvariableDTO->getUsr());

        $MaterialSignInvariableDTO->setProfile($UserProfileUid = new UserProfileUid(UserProfileUid::TEST));
        self::assertSame($UserProfileUid, $MaterialSignInvariableDTO->getProfile());

        $MaterialSignInvariableDTO->setPart(MaterialSignUid::TEST);
        self::assertSame(MaterialSignUid::TEST, $MaterialSignInvariableDTO->getPart());


        $MaterialUid = new MaterialUid(MaterialUid::TEST);
        $MaterialSignInvariableDTO->setMaterial($MaterialUid);
        self::assertSame($MaterialUid, $MaterialSignInvariableDTO->getMaterial());

        $MaterialOfferConst = new MaterialOfferConst(MaterialOfferConst::TEST);
        $MaterialSignInvariableDTO->setOffer($MaterialOfferConst);
        self::assertSame($MaterialOfferConst, $MaterialSignInvariableDTO->getOffer());

        $MaterialVariationConst = new MaterialVariationConst(MaterialVariationConst::TEST);
        $MaterialSignInvariableDTO->setVariation($MaterialVariationConst);
        self::assertSame($MaterialVariationConst, $MaterialSignInvariableDTO->getVariation());

        $MaterialModificationConst = new MaterialModificationConst(MaterialModificationConst::TEST);
        $MaterialSignInvariableDTO->setModification($MaterialModificationConst);
        self::assertSame($MaterialModificationConst, $MaterialSignInvariableDTO->getModification());


        /** @var MaterialSignHandler $MaterialSignHandler */
        $MaterialSignHandler = self::getContainer()->get(MaterialSignHandler::class);
        $handle = $MaterialSignHandler->handle($MaterialSignDTO);

        self::assertTrue(($handle instanceof MaterialSign), $handle.': Ошибка MaterialSign');

    }
}
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

namespace BaksDev\Materials\Sign\UseCase\Admin\Status\Tests;

use BaksDev\Materials\Category\UseCase\Admin\NewEdit\Tests\CategoryMaterialNewTest;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\CurrentEvent\MaterialSignCurrentEventInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignCancelDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
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
final class MaterialSignCancelHandleTest extends KernelTestCase
{
    #[DependsOnClass(MaterialSignProcessHandleTest::class)]
    public function testUseCase(): void
    {
        // Бросаем событие консольной комманды
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $event = new ConsoleCommandEvent(new Command(), new StringInput(''), new NullOutput());
        $dispatcher->dispatch($event, 'console.command');


        /** @var MaterialSignCurrentEventInterface $MaterialSignCurrentEvent */
        $MaterialSignCurrentEvent = self::getContainer()->get(MaterialSignCurrentEventInterface::class);
        $MaterialSignEvent = $MaterialSignCurrentEvent
            ->forMaterialSign(MaterialSignUid::TEST)
            ->find();

        self::assertInstanceOf(MaterialSignEvent::class, $MaterialSignEvent);

        /** @see MaterialSignCancelDTO */

        $MaterialSignDTO = new MaterialSignCancelDTO();
        $MaterialSignEvent->getDto($MaterialSignDTO);

        self::assertNull($MaterialSignDTO->getInvariable()->getSeller());

        /** При отмене присваивается статус New «Новый» */
        self::assertTrue($MaterialSignDTO->getStatus()->equals(MaterialSignStatusNew::class));
        self::assertNull($MaterialSignDTO->getOrd());


        /** @var MaterialSignStatusHandler $MaterialSignStatusHandler */
        $MaterialSignStatusHandler = self::getContainer()->get(MaterialSignStatusHandler::class);
        $handle = $MaterialSignStatusHandler->handle($MaterialSignDTO);

        self::assertTrue(($handle instanceof MaterialSign), $handle.': Ошибка MaterialSign');

    }
}
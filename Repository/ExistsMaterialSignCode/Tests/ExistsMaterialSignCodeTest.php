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

namespace BaksDev\Materials\Sign\Repository\ExistsMaterialSignCode\Tests;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\ExistsMaterialSignCode\ExistsMaterialSignCodeInterface;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group materials-sign
 */
#[When(env: 'test')]
class ExistsMaterialSignCodeTest extends KernelTestCase
{
    private static UserUid|false $usr = false;

    private static string|false $code = false;

    public static function setUpBeforeClass(): void
    {
        /** @var DBALQueryBuilder $DBALQueryBuilder */
        $DBALQueryBuilder = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal = $DBALQueryBuilder->createQueryBuilder(self::class);

        $result = $dbal
            ->from(MaterialSign::class, 'sign')
            ->addSelect('code.code')
            ->leftJoin(
                'sign',
                MaterialSignCode::class,
                'code',
                'code.main = sign.id'
            )
            ->addSelect('invariable.usr')
            ->leftJoin(
                'sign',
                MaterialSignInvariable::class,
                'invariable',
                'invariable.main = sign.id'
            )->fetchAssociative();


        self::$usr = isset($result['usr']) ? new UserUid($result['usr']) : false;
        self::$code = $result['code'] ?? false;

    }

    public function testUseCase(): void
    {
        if(self::$usr instanceof UserUid)
        {
            /** @var ExistsMaterialSignCodeInterface $ExistsMaterialSignCodeInterface */
            $ExistsMaterialSignCodeInterface = self::getContainer()->get(ExistsMaterialSignCodeInterface::class);
            $ExistsMaterialSignCodeEvent = $ExistsMaterialSignCodeInterface->isExists(
                self::$usr,
                self::$code
            );

            self::assertTrue($ExistsMaterialSignCodeEvent);
        }
        else
        {
            echo PHP_EOL."В базе отсутствует «Честный знак»! ".self::class.':'.__LINE__.PHP_EOL;
            self::assertTrue(true);
        }
    }
}

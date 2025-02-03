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

namespace BaksDev\Materials\Sign\Repository\ExistsMaterialSignCode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusError;
use BaksDev\Users\User\Type\Id\UserUid;

final class ExistsMaterialSignCodeRepository implements ExistsMaterialSignCodeInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /** Метод проверяет имеется ли у пользователя такой код (Без ошибки)  */
    public function isExists(UserUid $user, string $code): bool
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->from(MaterialSignCode::class, 'sign_code')
            ->where('sign_code.code = :code')
            ->setParameter('code', $code);

        $dbal
            ->join(
                'sign_code',
                MaterialSign::class,
                'sign',
                'sign.id = sign_code.main'
            );

        $dbal
            ->join(
                'sign_code',
                MaterialSignInvariable::class,
                'invariable',
                'invariable.main = sign_code.main AND invariable.usr = :usr'
            )
            ->setParameter(
                'usr',
                $user,
                UserUid::TYPE
            );


        $dbal
            ->join(
                'sign',
                MaterialSignEvent::class,
                'event',
                'event.id = sign.event AND event.status != :status'
            )
            ->setParameter(
                'status',
                MaterialSignStatusError::class,
                MaterialSignStatus::TYPE
            );

        return $dbal->fetchExist();
    }
}

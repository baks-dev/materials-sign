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

namespace BaksDev\Materials\Sign\Repository\MaterialSignCode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use InvalidArgumentException;

final class MaterialSignCodeRepository implements MaterialSignCodeInterface
{
    private MaterialSignUid|false $sign = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forMaterialSign(MaterialSignUid|string $sign): self
    {
        if(empty($sign))
        {
            $this->sign = false;
            return $this;
        }

        if(is_string($sign))
        {
            $sign = new MaterialSignUid($sign);
        }

        $this->sign = $sign;

        return $this;
    }

    /**
     * Метод возвращает QR-код честного знака
     */
    public function find(): MaterialSignCodeResult|bool
    {
        if(false === ($this->sign instanceof MaterialSignUid))
        {
            throw new InvalidArgumentException('Invalid Argument MaterialSign');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->addSelect('code.main')
            ->addSelect('code.code')
            ->addSelect("CONCAT('/upload/".$dbal->table(MaterialSignCode::class)."' , '/', code.name) AS name")
            ->addSelect('code.ext')
            ->addSelect('code.cdn')
            ->from(MaterialSignCode::class, 'code')
            ->where('code.main = :sign')
            ->setParameter(
                key: 'sign',
                value: $this->sign,
                type: MaterialSignUid::TYPE
            );

        return $dbal
            ->enableCache('materials-sign')
            ->fetchHydrate(MaterialSignCodeResult::class);
    }
}
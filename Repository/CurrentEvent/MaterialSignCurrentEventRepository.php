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

namespace BaksDev\Materials\Sign\Repository\CurrentEvent;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use InvalidArgumentException;

final class MaterialSignCurrentEventRepository implements MaterialSignCurrentEventInterface
{
    private MaterialSignUid|false $sign = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forMaterialSign(MaterialSign|MaterialSignUid|string $sign): self
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

        if($sign instanceof MaterialSign)
        {
            $sign = $sign->getId();
        }

        $this->sign = $sign;

        return $this;
    }

    /**
     * Возвращает активное событие
     */
    public function find(): MaterialSignEvent|false
    {
        if(false === ($this->sign instanceof MaterialSignUid))
        {
            throw new InvalidArgumentException('Invalid Argument MaterialSign');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->from(MaterialSign::class, 'main')
            ->where('main.id = :main')
            ->setParameter(
                key: 'main',
                value: $this->sign,
                type: MaterialSignUid::TYPE
            );

        $orm
            ->select('event')
            ->join(
            MaterialSignEvent::class,
            'event',
            'WITH',
            'event.id = main.event'
        );

        return $orm->getOneOrNullResult() ?: false;
    }
}
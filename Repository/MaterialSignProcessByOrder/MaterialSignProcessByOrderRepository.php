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

namespace BaksDev\Materials\Sign\Repository\MaterialSignProcessByOrder;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusProcess;
use BaksDev\Orders\Order\Type\Id\OrderUid;

final class MaterialSignProcessByOrderRepository implements MaterialSignProcessByOrderInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /**
     * Метод возвращает события Честный знак по заказу со статусом Process «В процессе»
     */
    public function findByOrder(OrderUid $order): ?array
    {
        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm
            ->select('event')
            ->from(MaterialSignEvent::class, 'event');

        $orm
            ->where('event.ord = :ord')
            ->setParameter('ord', $order, OrderUid::TYPE);

        $orm->andWhere('event.status = :status')
            ->setParameter('status', new MaterialSignStatus(MaterialSignStatusProcess::class), MaterialSignStatus::TYPE);

        $orm->join(
            MaterialSign::class,
            'main',
            'WITH',
            'main.event = event.id'
        );

        return $orm->getQuery()->getResult();
    }
}
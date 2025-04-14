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

namespace BaksDev\Materials\Sign\Repository\MaterialSignProcessByOrderMaterial;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusProcess;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use InvalidArgumentException;

final class MaterialSignProcessByOrderProductRepository implements MaterialSignProcessByOrderProductInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    private OrderUid $order;

    private MaterialOfferConst|false $offer = false;

    private MaterialVariationConst|false $variation = false;

    private MaterialModificationConst|false $modification = false;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    public function forOrder(Order|OrderUid|string $order): self
    {
        if($order instanceof Order)
        {
            $order = $order->getId();
        }

        if(is_string($order))
        {
            $order = new OrderUid($order);
        }

        $this->order = $order;

        return $this;
    }

    public function forOfferConst(MaterialOfferConst|string|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        if(is_string($offer))
        {
            $offer = new MaterialOfferConst($offer);
        }

        $this->offer = $offer;

        return $this;
    }

    public function forVariationConst(MaterialVariationConst|string|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        if(is_string($variation))
        {
            $variation = new MaterialVariationConst($variation);
        }

        $this->variation = $variation;

        return $this;
    }

    public function forModificationConst(MaterialModificationConst|string|null|false $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        if(is_string($modification))
        {
            $modification = new MaterialModificationConst($modification);
        }

        $this->modification = $modification;

        return $this;
    }


    /**
     * Метод возвращает Честный знак на продукцию по заказу со статусом Process «В процессе»
     */
    public function find(): MaterialSignEvent|false
    {
        if(!isset($this->order))
        {
            throw new InvalidArgumentException('Не определен обязательный параметр order');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->select('event');

        $orm->from(MaterialSignEvent::class, 'event');

        $orm
            ->where('event.ord = :ord')
            ->setParameter(
                'ord',
                $this->order,
                OrderUid::TYPE
            );

        $orm
            ->andWhere('event.status = :status')
            ->setParameter(
                'status',
                MaterialSignStatusProcess::class,
                MaterialSignStatus::TYPE
            );

        $orm->join(
            MaterialSign::class,
            'main',
            'WITH',
            'main.event = event.id'
        );

        $orm->join(
            MaterialSignInvariable::class,
            'invariable',
            'WITH',
            'invariable.event = event.id'
        );


        if($this->offer instanceof MaterialOfferConst)
        {
            $orm
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    'offer',
                    $this->offer,
                    MaterialOfferConst::TYPE
                );
        }
        else
        {
            $orm->andWhere('invariable.offer IS NULL');
        }

        if($this->variation instanceof MaterialVariationConst)
        {
            $orm
                ->andWhere('invariable.variation = :variation')
                ->setParameter(
                    'variation',
                    $this->variation,
                    MaterialVariationConst::TYPE
                );

        }
        else
        {
            $orm->andWhere('invariable.variation IS NULL');
        }


        if($this->modification instanceof MaterialModificationConst)
        {
            $orm
                ->andWhere('invariable.modification = :modification')
                ->setParameter(
                    'modification',
                    $this->modification,
                    MaterialModificationConst::TYPE
                );

        }
        else
        {
            $orm->andWhere('invariable.modification IS NULL');
        }

        $orm->setMaxResults(1);

        return $orm->getQuery()->getOneOrNullResult() ?: false;
    }
}

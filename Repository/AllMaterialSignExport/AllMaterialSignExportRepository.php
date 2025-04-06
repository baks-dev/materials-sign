<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Materials\Sign\Repository\AllMaterialSignExport;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Delivery\Entity\Event\DeliveryEvent;
use BaksDev\Delivery\Entity\Trans\DeliveryTrans;
use BaksDev\Materials\Catalog\Entity\Event\MaterialEvent;
use BaksDev\Materials\Catalog\Entity\Offers\MaterialOffer;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\MaterialVariation;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\MaterialModification;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Orders\Order\Entity\Materials\OrderMaterial;
use BaksDev\Orders\Order\Entity\Materials\Price\OrderPrice;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Orders\Order\Type\Status\OrderStatus;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusCompleted;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Generator;
use InvalidArgumentException;

final class AllMaterialSignExportRepository implements AllMaterialSignExportInterface
{
    private UserProfileUid|false $profile = false;

    private DateTimeImmutable|false $from = false;

    private DateTimeImmutable|false $to = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}

    public function forProfile(UserProfile|UserProfileUid|string $profile): self
    {
        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        $this->profile = $profile;

        return $this;
    }

    public function dateFrom(DateTimeImmutable|string $from): self
    {
        if(is_string($from))
        {
            $from = new DateTimeImmutable($from);
        }

        $this->from = $from;

        return $this;

    }

    public function dateTo(DateTimeImmutable|string $to): self
    {
        if(is_string($to))
        {

            $to = new DateTimeImmutable($to);
        }

        $this->to = $to;

        return $this;
    }


    public function execute(): Generator|false
    {
        if($this->profile === false)
        {
            throw new InvalidArgumentException('Invalid Argument profile');
        }

        if($this->from === false)
        {
            throw new InvalidArgumentException('Invalid Argument from date');
        }

        if($this->to === false)
        {
            throw new InvalidArgumentException('Invalid Argument to date');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->from(OrderEvent::class, 'event');

        $dbal->where('event.status = :status')
            ->setParameter(
                'status',
                OrderStatusCompleted::class,
                OrderStatus::TYPE
            );

        $dbal
            ->andWhere('event.created BETWEEN :start AND :end')
            ->setParameter('start', $this->from, Types::DATETIME_IMMUTABLE)
            ->setParameter('end', $this->to, Types::DATETIME_IMMUTABLE);


        $dbal
            ->join(
                'event',
                Order::class,
                'main',
                'main.event = event.id'
            );


        $dbal
            ->addSelect('order_invariable.number')
            ->join(
                'main',
                OrderInvariable::class,
                'order_invariable',
                'order_invariable.main = main.id AND 
                order_invariable.event = event.id AND 
                order_invariable.profile = :profile'
            )
            ->setParameter(
                'profile',
                $this->profile,
                UserProfileUid::TYPE
            );


        $dbal
            ->leftJoin(
                'main',
                OrderUser::class,
                'order_user',
                'order_user.event = main.event'
            );

        $dbal
            ->addSelect('order_delivery.delivery_date AS delivery_date')
            ->leftJoin(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                'order_delivery.usr = order_user.id'
            );

        $dbal
            ->addSelect('delivery_event.sort AS delivery_sort')
            ->leftJoin(
                'order_delivery',
                DeliveryEvent::class,
                'delivery_event',
                'delivery_event.id = order_delivery.event'
            );

        $dbal
            ->addSelect('delivery_trans.name AS delivery_name')
            ->leftJoin(
                'order_delivery',
                DeliveryTrans::class,
                'delivery_trans',
                'delivery_trans.event = order_delivery.event AND delivery_trans.local = :local'
            );

        $dbal
            ->leftJoin(
                'event',
                OrderMaterial::class,
                'order_material',
                'order_material.event = event.id'
            );


        $dbal
            ->addSelect('SUM(order_price.price) AS order_total')
            ->leftJoin(
                'event',
                OrderPrice::class,
                'order_price',
                'order_price.material = order_material.id'
            );

        $dbal
            ->leftJoin(
                'order_material',
                MaterialEvent::class,
                'material_event',
                'material_event.id = order_material.material'
            );

        $dbal
            ->leftJoin(
                'order_material',
                MaterialOffer::class,
                'material_offer',
                'material_offer.id = order_material.offer AND material_offer.event = order_material.material'
            );

        $dbal
            ->leftJoin(
                'order_material',
                MaterialVariation::class,
                'material_variation',
                'material_variation.id = order_material.variation AND material_variation.offer = material_offer.id'
            );

        $dbal
            ->leftJoin(
                'order_material',
                MaterialModification::class,
                'material_modification',
                'material_modification.id = order_material.modification AND material_modification.variation = material_variation.id'
            );


        $dbal
            ->leftJoin(
                'main',
                MaterialSignEvent::class,
                'sign_event',
                '
                    sign_event.ord = main.id AND
                    sign_event.status = :sign_status
                '
            )
            ->setParameter(
                'sign_status',
                MaterialSignStatusDone::class,
                MaterialSignStatus::TYPE
            );

        $dbal
            ->leftJoin(
                'material_modification',
                MaterialSignInvariable::class,
                'sign_invariable',
                '
                    sign_invariable.event = sign_event.id AND
                    sign_invariable.main = sign_event.main AND
                    
                    sign_invariable.material = material_event.main AND
                    sign_invariable.offer = material_offer.const AND
                    sign_invariable.variation = material_variation.const AND
                    sign_invariable.modification = material_modification.const
                '
            );


        $dbal
            ->leftJoin(
                'sign_invariable',
                MaterialSignCode::class,
                'sign_code',
                '
                    sign_code.main = sign_invariable.main AND 
                    sign_code.event = sign_invariable.event
                '
            );


        $dbal->addSelect(
            "JSON_AGG
                    ( DISTINCT
        
                            JSONB_BUILD_OBJECT
                            (
                                'article', material_modification.article,
                                'price', order_price.price,
                                'code', sign_code.code
                            )
        
                    ) AS materials"
        );


        $dbal->allGroupByExclude();

        return $dbal->fetchAllGenerator();
    }
}

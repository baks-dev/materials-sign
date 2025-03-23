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

namespace BaksDev\Materials\Sign\Repository\MaterialSignByOrder;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusProcess;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Entity\Event\UserProfileEvent;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use InvalidArgumentException;

final class MaterialSignByOrderRepository implements MaterialSignByOrderInterface
{
    /** Фильтр по сырью */

    private MaterialUid|false $material = false;

    private MaterialOfferConst|false $offer = false;

    private MaterialVariationConst|false $variation = false;

    private MaterialModificationConst|false $modification = false;

    /** Фильтр по заказу */

    private OrderUid|false $order = false;

    private UserProfileUid|false $profile = false;

    private MaterialSignStatus $status;


    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        /** По умолчанию возвращаем знаки со статусом Process «В процессе» */
        $this->status = new MaterialSignStatus(MaterialSignStatusProcess::class);
    }

    public function material(Material|MaterialUid|string $material): self
    {
        if(is_string($material))
        {
            $material = new MaterialUid($material);
        }

        if($material instanceof Material)
        {

            $material = $material->getId();
        }

        $this->material = $material;

        return $this;
    }

    public function offer(MaterialOfferConst|string|null|false $offer): self
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

    public function variation(MaterialVariationConst|string|null|false $variation): self
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

    public function modification(MaterialModificationConst|string|null|false $modification): self
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

    public function profile(UserProfileUid|string|UserProfile $profile): self
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;

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

    /**
     * Возвращает знаки со статусом Done «Выполнен»
     */
    public function withStatusDone(): self
    {
        $this->status = new MaterialSignStatus(MaterialSignStatusDone::class);
        return $this;
    }

    /**
     * Метод возвращает все штрихкоды «Честный знак» для печати по идентификатору заказа
     * По умолчанию возвращает знаки со статусом Process «В процессе»
     */
    public function findAll(): array|false
    {
        if($this->order === false)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр order через вызов метода ->forOrder(...)');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal->from(
            MaterialSignEvent::class,
            'event'
        );

        $dbal
            ->where('event.ord = :ord')
            ->setParameter('ord', $this->order, OrderUid::TYPE);

        $dbal
            ->andWhere('event.status = :status')
            ->setParameter('status', $this->status, MaterialSignStatus::TYPE);

        if($this->profile !== false)
        {
            $dbal->leftJoin(
                'event',
                Order::class,
                'ord',
                'ord.id = event.ord'
            );


            $dbal->leftJoin(
                'ord',
                OrderUser::class,
                'ord_usr',
                'ord_usr.event = ord.event'
            );

            $dbal
                ->join(
                    'ord_usr',
                    UserProfileEvent::class,
                    'profile_event',
                    'profile_event.id = ord_usr.profile AND profile_event.profile = :profile'
                )
                ->setParameter('profile', $this->profile, UserProfileUid::TYPE);
        }

        $dbal
            ->addSelect('main.id')
            ->join(
            'event',
            MaterialSign::class,
            'main',
            'main.id = event.main'
        );


        if($this->material)
        {
            $offerParam = $this->offer ? ' = :offer' : ' IS NULL';
            !$this->offer ?: $dbal->setParameter('offer', $this->offer, MaterialOfferConst::TYPE);

            $variationParam = $this->variation ? ' = :variation' : ' IS NULL';
            !$this->variation ?: $dbal->setParameter('variation', $this->variation, MaterialVariationConst::TYPE);

            $modificationParam = $this->modification ? ' = :modification' : ' IS NULL';
            !$this->modification ?: $dbal->setParameter('modification', $this->modification, MaterialModificationConst::TYPE);

            $dbal
                ->join(
                    'event',
                    MaterialSignInvariable::class,
                    'invariable',
                    '
                    invariable.main = main.id AND 
                    invariable.material = :material AND
                    invariable.offer '.$offerParam.' AND
                    invariable.variation '.$variationParam.' AND
                    invariable.modification '.$modificationParam.'
                '
                )
                ->setParameter(
                    'material',
                    $this->material,
                    MaterialUid::TYPE
                );
        }


        $dbal
            ->addSelect(
                "
                CASE
                   WHEN code.name IS NOT NULL 
                   THEN CONCAT ( '/upload/".$dbal->table(MaterialSignCode::class)."' , '/', code.name)
                   ELSE NULL
                END AS code_image
            "
            )
            ->addSelect("code.ext AS code_ext")
            ->addSelect("code.cdn AS code_cdn")
            ->addSelect("code.event AS code_event")
            ->addSelect("code.code AS code_string")
            ->leftJoin(
                'event',
                MaterialSignCode::class,
                'code',
                'code.main = main.id'
            );


        return $dbal
            // ->enableCache('Namespace', 3600)
            ->fetchAllAssociative() ?: false;
    }


}

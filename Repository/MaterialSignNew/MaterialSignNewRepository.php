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

namespace BaksDev\Materials\Sign\Repository\MaterialSignNew;

use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Entity\Modify\MaterialSignModify;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use InvalidArgumentException;

final class MaterialSignNewRepository implements MaterialSignNewInterface
{
    private UserUid $user;

    private UserProfileUid $profile;

    private MaterialUid $material;

    private MaterialOfferConst|false $offer = false;

    private MaterialVariationConst|false $variation = false;

    private MaterialModificationConst|false $modification = false;

    public function __construct(private readonly ORMQueryBuilder $ORMQueryBuilder) {}

    public function forUser(User|UserUid|string $user): self
    {
        if($user instanceof User)
        {
            $user = $user->getId();
        }

        if(is_string($user))
        {
            $user = new UserUid($user);
        }

        $this->user = $user;

        return $this;
    }

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

    public function forMaterial(Material|MaterialUid|string $material): self
    {

        if($material instanceof Material)
        {
            $material = $material->getId();
        }

        if(is_string($material))
        {
            $material = new MaterialUid($material);
        }

        $this->material = $material;

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
     * Метод возвращает один Честный знак на указанную продукцию со статусом New «Новый»
     * если у владельца имеется Честный знак - возвращает
     * если нет у владельца - берет у партнера самый старый
     */
    public function getOneMaterialSign(): MaterialSignEvent|false
    {
        if(!isset($this->user, $this->profile, $this->material))
        {
            throw new InvalidArgumentException('Не определено обязательное свойство user, profile, либо material');
        }

        $orm = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $orm->from(MaterialSignInvariable::class, 'invariable');

        $orm
            ->where('invariable.usr = :usr')
            ->setParameter(
                key: 'usr',
                value: $this->user,
                type: UserUid::TYPE
            );

        $orm
            ->andWhere('invariable.material = :material')
            ->setParameter(
                key: 'material',
                value: $this->material,
                type: MaterialUid::TYPE
            );


        $orm
            ->andWhere('(invariable.seller IS NULL OR invariable.seller = :seller)')
            ->setParameter(
                key: 'seller',
                value: $this->profile,
                type: UserProfileUid::TYPE
            );

        if($this->offer instanceof MaterialOfferConst)
        {
            $orm
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: MaterialOfferConst::TYPE
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
                    key: 'variation',
                    value: $this->variation,
                    type: MaterialVariationConst::TYPE
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
                    key: 'modification',
                    value: $this->modification,
                    type: MaterialModificationConst::TYPE
                );
        }
        else
        {
            $orm->andWhere('invariable.modification IS NULL');
        }


        $orm->join(
            MaterialSign::class,
            'main',
            'WITH',
            'main.id = invariable.main'
        );

        $orm
            ->select('event')
            ->join(
                MaterialSignEvent::class,
                'event',
                'WITH',
                '
                event.id = main.event AND 
                event.status = :status
            ')
            ->setParameter(
                key: 'status',
                value: MaterialSignStatusNew::class,
                type: MaterialSignStatus::TYPE
            );

        $orm
            ->leftJoin(
                MaterialSignModify::class,
                'modify',
                'WITH',
                'modify.event = main.event'
            );

        /**
         *  Сортировка по профилю:
         *  если у владельца имеется Честный знак - возвращает
         *  если нет у владельца - берет у партнера
         */

        $orm
            ->addOrderBy("CASE invariable.profile WHEN :profile THEN false ELSE true END")
            ->setParameter(
                key: 'profile',
                value: $this->profile,
                type: UserProfileUid::TYPE
            );

        /** Сортируем по дате, выбирая самый старый знак */
        $orm->addOrderBy('modify.modDate');

        $orm->setMaxResults(1);

        return $orm->getOneOrNullResult() ?: false;
    }


}

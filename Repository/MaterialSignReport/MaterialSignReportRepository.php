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

namespace BaksDev\Materials\Sign\Repository\MaterialSignReport;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Entity\Offers\MaterialOffer;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\MaterialVariation;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\MaterialModification;
use BaksDev\Materials\Catalog\Entity\Trans\MaterialTrans;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Category\Entity\Offers\CategoryMaterialOffers;
use BaksDev\Materials\Category\Entity\Offers\Variation\CategoryMaterialVariation;
use BaksDev\Materials\Category\Entity\Offers\Variation\Modification\CategoryMaterialModification;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\Modify\MaterialSignModify;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusProcess;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\Products\Price\OrderPrice;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Generator;
use InvalidArgumentException;

final class MaterialSignReportRepository implements MaterialSignReportInterface
{
    private UserProfileUid|false $profile = false;

    private UserProfileUid|false $seller = false;

    private DateTimeImmutable $from;

    private DateTimeImmutable $to;

    private MaterialUid|false $material = false;

    private MaterialOfferConst|false $offer = false;

    private MaterialVariationConst|false $variation = false;

    private MaterialModificationConst|false $modification = false;

    private string|false $status = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        $this->from = new DateTimeImmutable('now');
        $this->to = new DateTimeImmutable('now');
    }

    public function fromProfile(UserProfile|UserProfileUid|string $profile): self
    {
        if(empty($profile))
        {
            $this->profile = false;
            return $this;
        }

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

    public function fromSeller(UserProfileUid|string $seller): self
    {
        if(empty($seller))
        {
            $this->seller = false;
            return $this;
        }

        if(is_string($seller))
        {
            $seller = new UserProfileUid($seller);
        }

        $this->seller = $seller;

        return $this;
    }


    public function dateFrom(DateTimeImmutable $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function dateTo(DateTimeImmutable $to): self
    {
        $this->to = $to;

        return $this;
    }


    /**
     * Material
     */

    public function setMaterial(MaterialUid|string|null|false $material): self
    {
        if(empty($material))
        {
            $this->material = false;

            return $this;
        }

        if(is_string($material))
        {
            $material = new MaterialUid($material);
        }

        $this->material = $material;

        return $this;
    }

    public function setOffer(MaterialOfferConst|string|null|false $offer): self
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

    public function setVariation(MaterialVariationConst|string|null|false $variation): self
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

    public function setModification(MaterialModificationConst|string|null|false $modification): self
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

    public function onlyStatusDone(): self
    {
        $this->status = MaterialSignStatusDone::STATUS;

        return $this;
    }

    public function onlyStatusProcess(): self
    {
        $this->status = MaterialSignStatusProcess::class;

        return $this;
    }

    /**
     * Метод получает все реализованные честные знаки
     * @return Generator{int, MaterialSignReportResult}|false
     */
    public function findAll(): Generator|false
    {

        if(false === $this->status)
        {
            throw new InvalidArgumentException('Invalid Argument Status');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->from(MaterialSignInvariable::class, 'invariable');

        /** Если не передан владелец - обязательно должен быть передан Seller для отчета о выводе из оборота (розничная продажа) */

        if(false === ($this->profile instanceof UserProfileUid))
        {
            if(false === ($this->seller instanceof UserProfileUid))
            {
                throw new InvalidArgumentException('Invalid Argument Seller');
            }

            /** Всегда получаем КИЗЫ по продавцу если нет владельца */
            $dbal
                ->andWhere('invariable.seller = :seller')
                ->setParameter(
                    key: 'seller',
                    value: $this->seller,
                    type: UserProfileUid::TYPE
                );
        }


        /** Если передан Владелец - получаем КИЗЫ для передачи между юр лицам  */
        if($this->profile instanceof UserProfileUid)
        {
            $dbal
                ->andWhere('invariable.profile = :profile')
                ->setParameter(
                    key: 'profile',
                    value: $this->profile,
                    type: UserProfileUid::TYPE
                );

            /** Получаем все КИЗЫ для передачи */
            if(false === ($this->seller instanceof UserProfileUid))
            {
                $dbal->andWhere('invariable.profile != invariable.seller ');
            }

            /** Получаем КИЗЫ для передачи по продавцу */
            else
            {
                $dbal
                    ->andWhere('invariable.seller = :seller')
                    ->setParameter(
                        key: 'seller',
                        value: $this->seller,
                        type: UserProfileUid::TYPE
                    );
            }
        }

        $dbal->addSelect('invariable.seller');


        if($this->material)
        {
            $dbal
                ->andWhere('invariable.material = :material')
                ->setParameter(
                    key: 'material',
                    value: $this->material,
                    type: MaterialUid::TYPE
                );
        }

        if($this->offer)
        {
            $dbal
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: MaterialOfferConst::TYPE
                );
        }

        if($this->variation)
        {
            $dbal
                ->andWhere('invariable.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: MaterialVariationConst::TYPE
                );
        }

        if($this->modification)
        {
            $dbal
                ->andWhere('invariable.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: MaterialModificationConst::TYPE
                );
        }


        $dbal
            ->join(
                'invariable',
                MaterialSignEvent::class,
                'event',
                'event.main = invariable.main AND event.status = :status'
            )
            ->setParameter(
                key: 'status',
                value: $this->status,
                type: MaterialSignStatus::TYPE
            );


        $dbal
            ->addSelect('modify.mod_date AS date')
            ->join(
                'invariable',
                MaterialSignModify::class,
                'modify',
                'modify.event = event.id AND DATE(modify.mod_date) BETWEEN :date_from AND :date_to'
            )
            ->setParameter('date_from', $this->from, Types::DATE_IMMUTABLE)
            ->setParameter('date_to', $this->to, Types::DATE_IMMUTABLE);

        $dbal
            //->addSelect('code.code')
            ->leftJoin(
                'invariable',
                MaterialSignCode::class,
                'code',
                'code.main = invariable.main'
            );


        /** Сырье */

        $dbal->join(
            'invariable',
            Material::class,
            'material',
            'material.id = invariable.material'
        );

        $dbal
            //->addSelect('material_trans.name as material_name')
            ->join(
                'material',
                MaterialTrans::class,
                'material_trans',
                'material_trans.event = material.event AND material_trans.local = :local'
            );

        /** Свойства торговых предложений */

        $dbal
            //->addSelect('material_offer.value as material_offer_value')
            ->leftJoin(
                'material',
                MaterialOffer::class,
                'material_offer',
                'material_offer.event = material.event AND material_offer.const = invariable.offer'
            );
        //->addOrderBy('material_offer.value');

        $dbal
            //->addSelect('material_variation.value as material_variation_value')
            ->leftJoin(
                'material_offer',
                MaterialVariation::class,
                'material_variation',
                'material_variation.offer = material_offer.id AND material_variation.const = invariable.variation'
            );
        //->addOrderBy('material_variation.value');

        $dbal
            //->addSelect('material_modification.value as material_modification_value')
            ->leftJoin(
                'material_variation',
                MaterialModification::class,
                'material_modification',
                'material_modification.variation = material_variation.id AND material_modification.const = invariable.modification'
            );
        //->addOrderBy('material_modification.value');


        /** Настройки категорий */

        $dbal
            //->addSelect('category_offer.reference as material_offer_reference')
            ->leftJoin(
                'material_offer',
                CategoryMaterialOffers::class,
                'category_offer',
                'category_offer.id = material_offer.category_offer'
            );

        $dbal
            ///->addSelect('category_variation.reference as material_variation_reference')
            ->leftJoin(
                'material_variation',
                CategoryMaterialVariation::class,
                'category_variation',
                'category_variation.id = material_variation.category_variation'
            );

        $dbal
            //->addSelect('category_offer_modification.reference as material_modification_reference')
            ->leftJoin(
                'material_modification',
                CategoryMaterialModification::class,
                'category_modification',
                'category_modification.id = material_modification.category_modification'
            );


        /** Информация о заказе */

        $dbal->leftJoin(
            'event',
            Order::class,
            'ord',
            'ord.id = event.ord'
        );


        $dbal
            ->addSelect('order_invariable.number')
            ->join(
                'event',
                OrderInvariable::class,
                'order_invariable',
                'order_invariable.main = event.ord'
            );

        $dbal
            ->leftJoin(
                'order_invariable',
                OrderProduct::class,
                'order_product',
                'order_product.event = order_invariable.event'
            );


        $dbal
            ->addSelect('SUM(order_price.price) AS total')
            ->leftJoin(
                'order_product',
                OrderPrice::class,
                'order_price',
                'order_price.product = order_product.id'
            );

        $dbal->addSelect(
            "JSON_AGG
                    ( DISTINCT
        
                            JSONB_BUILD_OBJECT
                            (
                                'name', material_trans.name,
                                
                                'offer_value', material_offer.value,
                                'offer_reference', category_offer.reference,
                                
                                'variation_value', material_variation.value,
                                'variation_reference', category_variation.reference,
                                
                                'modification_value', material_modification.value,
                                'modification_reference', category_modification.reference,
                                
                                'article', COALESCE(
                                    material_modification.article, 
                                    material_variation.article, 
                                    material_offer.article
                                ),

                                'price', order_price.price,
                                'count', order_price.total,
                                'code', code.code
                            )
        
                    ) AS products"
        );

        /**
         * Информация о продавце
         */


        $dbal->allGroupByExclude();

        $result = $dbal->fetchAllHydrate(MaterialSignReportResult::class);

        return $result->valid() ? $result : false;
    }
}
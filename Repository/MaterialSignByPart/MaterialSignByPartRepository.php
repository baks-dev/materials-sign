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

namespace BaksDev\Materials\Sign\Repository\MaterialSignByPart;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Entity\Offers\MaterialOffer;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\MaterialVariation;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\MaterialModification;
use BaksDev\Materials\Catalog\Entity\Trans\MaterialTrans;
use BaksDev\Materials\Category\Entity\Offers\CategoryMaterialOffers;
use BaksDev\Materials\Category\Entity\Offers\Variation\CategoryMaterialVariation;
use BaksDev\Materials\Category\Entity\Offers\Variation\Modification\CategoryMaterialModification;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDecommission;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusError;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Product;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use Generator;
use InvalidArgumentException;

final class MaterialSignByPartRepository implements MaterialSignByPartInterface
{
    private MaterialSignUid|false $part = false;

    private array $status;

    //private MaterialSignStatus $status;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder)
    {
        /** По умолчанию возвращаем знаки со статусом Decommission «Списано» */
        //$this->status[] = new MaterialSignStatus(MaterialSignStatusDecommission::class);
    }

    public function forPart(MaterialSignUid|string $part): self
    {
        if(is_string($part))
        {
            $part = new MaterialSignUid($part);
        }

        $this->part = $part;

        return $this;
    }

    /**
     * Возвращает знаки со статусом Done «Выполнен»
     */
    public function withStatusDone(): self
    {
        $this->status[] = new MaterialSignStatus(MaterialSignStatusDone::class);
        return $this;
    }

    /**
     * Возвращает знаки со статусом Decommission «Списано»
     */
    public function withStatusDecommission(): self
    {
        $this->status[] = new MaterialSignStatus(MaterialSignStatusDecommission::class);
        return $this;
    }

    /**
     * Возвращает знаки со статусом New «Новый»
     */
    public function withStatusNew(): self
    {
        $this->status[] = new MaterialSignStatus(MaterialSignStatusNew::class);
        return $this;
    }


    /**
     * Возвращает знаки со статусом Error «Ошибки»
     */
    public function withStatusError(): self
    {
        $this->status[] = new MaterialSignStatus(MaterialSignStatusError::class);
        return $this;
    }

    /**
     * Метод возвращает все штрихкоды «Честный знак» для печати по идентификатору артии
     * По умолчанию возвращает знаки со статусом Process «В процессе»
     *
     * @return Generator<MaterialSignByPartResult>|false
     */
    public function findAll(): Generator|false
    {
        if($this->part === false)
        {
            throw new InvalidArgumentException('Не передан обязательный параметр order через вызов метода ->forPart(...)');
        }

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal->from(
            MaterialSignInvariable::class,
            'invariable',
        );

        $dbal
            ->where('invariable.part = :part')
            ->setParameter('part', $this->part, MaterialSignUid::TYPE);

        $dbal
            ->addSelect('main.id AS sign_id')
            ->addSelect('main.event AS sign_event')
            ->join(
                'invariable',
                MaterialSign::class,
                'main',
                'main.id = invariable.main',
            );


        if(!empty($this->status))
        {
            $condition = null;

            foreach($this->status as $status)
            {
                $key = uniqid('status_', false);
                $condition[] = sprintf('event.status = :%s', $key);

                $dbal
                    ->setParameter(
                        $key,
                        $status,
                        MaterialSignStatus::TYPE,
                    );
            }

            $dbal
                ->join(
                    'invariable',
                    MaterialSignEvent::class,
                    'event',
                    sprintf('event.id = invariable.event AND (%s)', implode(' OR ', $condition)),
                );

        }


        $dbal
            ->addSelect('material.id AS material_id')
            ->addSelect('material.event AS material_event')
            ->leftJoin(
                'invariable',
                Material::class,
                'material',
                'material.id = invariable.material',
            );


        /** Название продукта */
        $dbal
            ->addSelect('material_trans.name AS material_name')
            ->leftJoin(
                'material',
                MaterialTrans::class,
                'material_trans',
                '
                        material_trans.event = material.event 
                        AND material_trans.local = :local
                    ');


        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('material_offer.value as material_offer_value')
            ->leftJoin(
                'material',
                MaterialOffer::class,
                'material_offer',
                'material_offer.event = material.event 
                AND material_offer.const = invariable.offer',
            );


        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as material_offer_reference')
            ->leftJoin(
                'material_offer',
                CategoryMaterialOffers::class,
                'category_offer',
                'category_offer.id = material_offer.category_offer',
            );


        /**
         * Множественные варианты торгового предложения
         */

        $dbal
            ->addSelect('material_variation.value as material_variation_value')
            ->leftJoin(
                'material_offer',
                MaterialVariation::class,
                'material_variation',
                'material_variation.offer = material_offer.id AND material_variation.const = invariable.variation',
            );


        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_variation.reference as material_variation_reference')
            ->leftJoin(
                'material_variation',
                CategoryMaterialVariation::class,
                'category_variation',
                'category_variation.id = material_variation.category_variation',
            );


        /**
         * Модификация множественного варианта торгового предложения
         */

        $dbal
            ->addSelect('material_modification.value as material_modification_value')
            ->leftJoin(
                'material_variation',
                MaterialModification::class,
                'material_modification',
                'material_modification.variation = material_variation.id AND material_modification.const = invariable.modification',
            );

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as material_modification_reference')
            ->leftJoin(
                'material_modification',
                CategoryMaterialModification::class,
                'category_offer_modification',
                'category_offer_modification.id = material_modification.category_modification',
            );


        $dbal
            ->addSelect("
                CASE
                   WHEN code.name IS NOT NULL 
                   THEN CONCAT ('/upload/barcode' , '/', code.name)
                   ELSE NULL
                END AS code_image
            ")
            ->addSelect("code.ext AS code_ext")
            ->addSelect("code.cdn AS code_cdn")
            ->addSelect("code.event AS code_event")
            ->addSelect("code.code AS code_string")
            ->leftJoin(
                'main',
                MaterialSignCode::class,
                'code',
                'code.main = main.id',
            );

        return $dbal
            // ->enableCache('Namespace', 3600)
            ->fetchAllHydrate(MaterialSignByPartResult::class) ?: false;
    }
}

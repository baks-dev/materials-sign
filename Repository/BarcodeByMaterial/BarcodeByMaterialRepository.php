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

namespace BaksDev\Materials\Sign\Repository\BarcodeByMaterial;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Entity\Modify\MaterialSignModify;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use InvalidArgumentException;
use BaksDev\Materials\Catalog\Type\Barcode\MaterialBarcode;

final class BarcodeByMaterialRepository implements BarcodeByMaterialInterface
{
    private MaterialUid|false $material = false;

    private MaterialOfferConst|false $offer = false;

    private MaterialVariationConst|false $variation = false;

    private MaterialModificationConst|false $modification = false;

    public function __construct(private readonly DBALQueryBuilder $DBALQueryBuilder) {}


    public function forMaterial(Material|MaterialUid $material): self
    {
        if($material instanceof Material)
        {
            $material = $material->getId();
        }

        $this->material = $material;

        return $this;
    }

    public function forOfferConst(MaterialOfferConst|null|false $offer): self
    {
        if(empty($offer))
        {
            $this->offer = false;
            return $this;
        }

        $this->offer = $offer;

        return $this;
    }

    public function forVariationConst(MaterialVariationConst|null|false $variation): self
    {
        if(empty($variation))
        {
            $this->variation = false;
            return $this;
        }

        $this->variation = $variation;

        return $this;
    }

    public function forModificationConst(MaterialModificationConst|null|false $modification): self
    {
        if(empty($modification))
        {
            $this->modification = false;
            return $this;
        }

        $this->modification = $modification;

        return $this;
    }

    /**
     * Метод возвращает идентификатор GTIN (barcode) сырья
     */
    public function find(): MaterialBarcode|false
    {

        if(false === ($this->material instanceof MaterialUid))
        {
            throw new InvalidArgumentException('Invalid Argument MaterialUid');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->from(MaterialSignInvariable::class, 'invariable')
            ->andWhere('invariable.material = :material')
            ->setParameter(
                key: 'material',
                value: $this->material,
                type: MaterialUid::TYPE,
            );


        if($this->offer instanceof MaterialOfferConst)
        {
            $dbal
                ->andWhere('invariable.offer = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->offer,
                    type: MaterialOfferConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('invariable.offer IS NULL');
        }


        if($this->variation instanceof MaterialVariationConst)
        {
            $dbal
                ->andWhere('invariable.variation = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->variation,
                    type: MaterialVariationConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('invariable.variation IS NULL');
        }

        if($this->modification instanceof MaterialModificationConst)
        {
            $dbal
                ->andWhere('invariable.modification = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->modification,
                    type: MaterialModificationConst::TYPE,
                );
        }
        else
        {
            $dbal->andWhere('invariable.modification IS NULL');
        }

        $dbal->join(
            'invariable',
            MaterialSign::class,
            'main',
            'main.id = invariable.main',
        );

        $dbal
            ->join(
                'invariable',
                MaterialSignEvent::class,
                'event',
                'event.id = invariable.event AND event.status = :status',
            )
            ->setParameter(
                key: 'status',
                value: MaterialSignStatusNew::class,
                type: MaterialSignStatus::TYPE,
            );

        $dbal->leftJoin(
            'invariable',
            MaterialSignModify::class,
            'modify',
            'modify.event = invariable.event',
        );

        $dbal
            ->select('code.code')
            ->join(
                'invariable',
                MaterialSignCode::class,
                'code',
                'code.main = invariable.main',
            );

        /** Сортируем по дате, выбирая самый НОВЫЙ знак */
        $dbal->addOrderBy('modify.mod_date', 'DESC');

        $dbal->setMaxResults(1);

        $code = $dbal->fetchOne();

        return $code ? new MaterialBarcode($code) : false;
    }
}
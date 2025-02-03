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

namespace BaksDev\Materials\Sign\Repository\NotExistsMaterialSignCode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Materials\Catalog\Entity\Category\MaterialCategory;
use BaksDev\Materials\Catalog\Entity\Event\MaterialEvent;
use BaksDev\Materials\Catalog\Entity\Info\MaterialInfo;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Entity\Offers\Image\MaterialOfferImage;
use BaksDev\Materials\Catalog\Entity\Offers\MaterialOffer;
use BaksDev\Materials\Catalog\Entity\Offers\Quantity\MaterialOfferQuantity;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Image\MaterialVariationImage;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\MaterialVariation;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\Image\MaterialModificationImage;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\MaterialModification;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\Quantity\MaterialModificationQuantity;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Quantity\MaterialVariationQuantity;
use BaksDev\Materials\Catalog\Entity\Photo\MaterialPhoto;
use BaksDev\Materials\Catalog\Entity\Price\MaterialPrice;
use BaksDev\Materials\Catalog\Entity\Property\MaterialProperty;
use BaksDev\Materials\Catalog\Entity\Trans\MaterialTrans;
use BaksDev\Materials\Catalog\Forms\MaterialFilter\Admin\MaterialFilterDTO;
use BaksDev\Materials\Catalog\Forms\MaterialFilter\Admin\Property\MaterialFilterPropertyDTO;
use BaksDev\Materials\Category\Entity\CategoryMaterial;
use BaksDev\Materials\Category\Entity\Info\CategoryMaterialInfo;
use BaksDev\Materials\Category\Entity\Offers\CategoryMaterialOffers;
use BaksDev\Materials\Category\Entity\Offers\Variation\CategoryMaterialVariation;
use BaksDev\Materials\Category\Entity\Offers\Variation\Modification\CategoryMaterialModification;
use BaksDev\Materials\Category\Entity\Trans\CategoryMaterialTrans;
use BaksDev\Materials\Category\Type\Id\CategoryMaterialUid;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusProcess;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\User\Type\Id\UserUid;

final class NotExistsMaterialSignCodeRepository implements NotExistsMaterialSignCodeInterface
{
    private ?SearchDTO $search = null;

    private ?MaterialFilterDTO $filter = null;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function filter(MaterialFilterDTO $filter): static
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Метод возвращает пагинатор сырья, которая имеется в наличии, но отсутствует «Честный знак»
     */
    public function findPaginator(): PaginatorInterface
    {
        $user = $this->userProfileTokenStorage->getUser();

        $profile = $this->userProfileTokenStorage->getProfile();

        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        // Material
        $dbal->addSelect('material.id as material_id');
        $dbal->addSelect('material.event as material_event');
        $dbal->from(
            Material::class,
            'material'
        );


        $dbal->leftJoin(
            'material',
            MaterialEvent::class,
            'material_event',
            'material_event.id = material.event'
        );

        $dbal
            ->leftJoin(
                'material',
                MaterialInfo::class,
                'material_info',
                'material_info.material = material.id'
            );


        $dbal
            ->addSelect('material_trans.name as material_name')
            ->join(
                'material',
                MaterialTrans::class,
                'material_trans',
                'material_trans.event = material.event AND material_trans.local = :local'
            );


        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('material_offer.const as material_offer_const')
            ->addSelect('material_offer.value as material_offer_value')
            ->leftJoin(
                'material',
                MaterialOffer::class,
                'material_offer',
                'material_offer.event = material.event'
            );


        if($this->filter?->getOffer())
        {
            $dbal->andWhere('material_offer.value = :offer');
            $dbal->setParameter('offer', $this->filter->getOffer());
        }


        // Получаем тип торгового предложения
        $dbal
            ->addSelect('category_offer.reference as material_offer_reference')
            ->leftJoin(
                'material_offer',
                CategoryMaterialOffers::class,
                'category_offer',
                'category_offer.id = material_offer.category_offer'
            );

        // Множественные варианты торгового предложения

        $dbal->addSelect('material_variation.const as material_variation_const')
            ->addSelect('material_variation.value as material_variation_value')
            ->leftJoin(
                'material_offer',
                MaterialVariation::class,
                'material_variation',
                'material_variation.offer = material_offer.id'
            );

        if($this->filter?->getVariation())
        {
            $dbal->andWhere('material_variation.value = :variation');
            $dbal->setParameter('variation', $this->filter->getVariation());
        }

        // Получаем тип множественного варианта
        $dbal
            ->addSelect('category_variation.reference as material_variation_reference')
            ->leftJoin(
                'material_variation',
                CategoryMaterialVariation::class,
                'category_variation',
                'category_variation.id = material_variation.category_variation'
            );


        // Модификация множественного варианта торгового предложения

        $dbal
            ->addSelect('material_modification.const as material_modification_const')
            ->addSelect('material_modification.value as material_modification_value')
            ->leftJoin(
                'material_variation',
                MaterialModification::class,
                'material_modification',
                'material_modification.variation = material_variation.id'
            );

        if($this->filter?->getModification())
        {
            $dbal->andWhere('material_modification.value = :modification');
            $dbal->setParameter('modification', $this->filter->getModification());
        }

        // Получаем тип модификации множественного варианта
        $dbal
            ->addSelect('category_offer_modification.reference as material_modification_reference')
            ->leftJoin(
                'material_modification',
                CategoryMaterialModification::class,
                'category_offer_modification',
                'category_offer_modification.id = material_modification.category_modification'
            );

        // Артикул сырья
        $dbal->addSelect(
            '
            COALESCE(
                material_modification.article,
                material_variation.article,
                material_offer.article,
                material_info.article
            ) AS material_article'
        );


        // Фото сырья

        $dbal->leftJoin(
            'material_modification',
            MaterialModificationImage::class,
            'material_modification_image',
            '
                material_modification_image.modification = material_modification.id AND
                material_modification_image.root = true
			'
        );

        $dbal->leftJoin(
            'material_offer',
            MaterialVariationImage::class,
            'material_variation_image',
            '
                material_variation_image.variation = material_variation.id AND
                material_variation_image.root = true
			'
        );

        $dbal->leftJoin(
            'material_offer',
            MaterialOfferImage::class,
            'material_offer_images',
            '
                material_variation_image.name IS NULL AND
                material_offer_images.offer = material_offer.id AND
                material_offer_images.root = true
			'
        );

        $dbal->leftJoin(
            'material_offer',
            MaterialPhoto::class,
            'material_photo',
            '
                material_offer_images.name IS NULL AND
                material_photo.event = material.event AND
                material_photo.root = true
			'
        );


        $dbal->addSelect(
            "
			CASE

			 WHEN material_modification_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(MaterialModificationImage::class)."' , '/', material_modification_image.name)
			   WHEN material_variation_image.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(MaterialVariationImage::class)."' , '/', material_variation_image.name)
			   WHEN material_offer_images.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(MaterialOfferImage::class)."' , '/', material_offer_images.name)
			   WHEN material_photo.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(MaterialPhoto::class)."' , '/', material_photo.name)
			   ELSE NULL
			END AS material_image
		"
        );


        // Расширение файла
        $dbal->addSelect(
            '
            COALESCE(
                material_modification_image.ext,
                material_variation_image.ext,
                material_offer_images.ext,
                material_photo.ext
            ) AS material_image_ext'
        );


        $dbal->addSelect(
            '
            COALESCE(
                material_modification_image.cdn,
                material_variation_image.cdn,
                material_offer_images.cdn,
                material_photo.cdn
            ) AS material_image_cdn'
        );


        // Категория
        $dbal
            ->addSelect('material_event_category.category AS material_category')
            ->leftJoin(
                'material_event',
                MaterialCategory::class,
                'material_event_category',
                'material_event_category.event = material_event.id AND material_event_category.root = true'
            );

        if($this->filter?->getCategory())
        {
            $dbal->andWhere('material_event_category.category = :category');
            $dbal->setParameter('category', $this->filter->getCategory(), CategoryMaterialUid::TYPE);
        }

        $dbal->leftJoin(
            'material_event_category',
            CategoryMaterial::class,
            'category',
            'category.id = material_event_category.category'
        );


        $dbal
            ->addSelect('category_trans.name AS category_name')
            ->leftJoin(
                'category',
                CategoryMaterialTrans::class,
                'category_trans',
                'category_trans.event = category.event AND category_trans.local = :local'
            );


        /* Наличие сырья */

        $dbal->leftJoin(
            'material',
            MaterialPrice::class,
            'material_price',
            'material_price.event = material.event'
        );

        /* Наличие и резерв торгового предложения */
        $dbal->leftJoin(
            'material_offer',
            MaterialOfferQuantity::class,
            'material_offer_quantity',
            'material_offer_quantity.offer = material_offer.id'
        );

        /* Наличие и резерв множественного варианта */
        $dbal->leftJoin(
            'material_variation',
            MaterialVariationQuantity::class,
            'material_variation_quantity',
            'material_variation_quantity.variation = material_variation.id'
        );

        $dbal
            ->leftJoin(
                'material_modification',
                MaterialModificationQuantity::class,
                'material_modification_quantity',
                'material_modification_quantity.modification = material_modification.id'
            );


        $dbal
            ->addSelect('
                COALESCE(
                    NULLIF(material_modification_quantity.quantity, 0),
                    NULLIF(material_variation_quantity.quantity, 0),
                    NULLIF(material_offer_quantity.quantity, 0),
                    NULLIF(material_price.quantity, 0),
                    0
                ) AS material_quantity
            ');


        $dbal->addSelect("
			COALESCE(
                NULLIF(material_modification_quantity.reserve, 0),
                NULLIF(material_variation_quantity.reserve, 0),
                NULLIF(material_offer_quantity.reserve, 0),
                NULLIF(material_price.reserve, 0),
                0
            ) AS material_reserve
		");


        $notExists = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $notExists
            ->select('COUNT(*)')
            ->from(MaterialSignInvariable::class, 'invariable')
            ->andWhere('invariable.usr = :usr')
            ->andWhere('invariable.material = material.id')
            ->andWhere('invariable.offer = material_offer.const')
            ->andWhere('invariable.variation = material_variation.const')
            ->andWhere('invariable.modification = material_modification.const');

        $notExists
            ->join(
                'invariable',
                MaterialSign::class,
                'sign_exists',
                'sign_exists.id = invariable.main'
            );

        $notExists
            ->join(
                'sign_exists',
                MaterialSignEvent::class,
                'event_exists',
                'event_exists.id = sign_exists.event AND (event_exists.status = :status_new OR event_exists.status = :status_progress)'
            );

        $dbal
            ->addSelect('
            (material_modification_quantity.quantity -
            material_variation_quantity.quantity - 
            material_offer_quantity.quantity -
            material_price.quantity)
             - ('.$notExists->getSQL().') AS counter');

        $dbal->setParameter('usr', $user, UserUid::TYPE);

        $dbal
            ->setParameter(
                'status_new',
                MaterialSignStatusNew::class,
                MaterialSignStatus::TYPE
            );

        $dbal
            ->setParameter(
                'status_progress',
                MaterialSignStatusProcess::class,
                MaterialSignStatus::TYPE
            );


        $dbal->andWhere('COALESCE(
                NULLIF(material_modification_quantity.quantity, 0),
                NULLIF(material_variation_quantity.quantity, 0),
                NULLIF(material_offer_quantity.quantity, 0),
                NULLIF(material_price.quantity, 0),
                0
            ) != 0');

        $dbal->andWhere('(material_modification_quantity.quantity -
            material_variation_quantity.quantity - 
            material_offer_quantity.quantity - 
            material_price.quantity) > ('.$notExists->getSQL().')');


        /**
         * Фильтр по свойства сырья
         */
        if($this->filter->getProperty())
        {
            /** @var MaterialFilterPropertyDTO $property */
            foreach($this->filter->getProperty() as $property)
            {
                if($property->getValue())
                {
                    $dbal->join(
                        'material',
                        MaterialProperty::class,
                        'material_property_'.$property->getType(),
                        'material_property_'.$property->getType().'.event = material.event AND 
                        material_property_'.$property->getType().'.field = :'.$property->getType().'_const AND 
                        material_property_'.$property->getType().'.value = :'.$property->getType().'_value'
                    );

                    $dbal->setParameter($property->getType().'_const', $property->getConst());
                    $dbal->setParameter($property->getType().'_value', $property->getValue());
                }
            }
        }

        /* Поиск */
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                //->addSearchLike('code.code')
                //->addSearchLike('orders.number')pro
                ->addSearchLike('material_modification.article')
                ->addSearchLike('material_variation.article')
                ->addSearchLike('material_offer.article')
                ->addSearchLike('material_info.article');
        }

        return $this->paginator->fetchAllAssociative($dbal);
    }
}

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

namespace BaksDev\Materials\Sign\Repository\AllMaterialSignPart;


use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Materials\Catalog\Entity\Category\MaterialCategory;
use BaksDev\Materials\Catalog\Entity\Event\MaterialEvent;
use BaksDev\Materials\Catalog\Entity\Info\MaterialInfo;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Entity\Offers\Image\MaterialOfferImage;
use BaksDev\Materials\Catalog\Entity\Offers\MaterialOffer;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Image\MaterialVariationImage;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\MaterialVariation;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\Image\MaterialModificationImage;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\MaterialModification;
use BaksDev\Materials\Catalog\Entity\Photo\MaterialPhoto;
use BaksDev\Materials\Catalog\Entity\Trans\MaterialTrans;
use BaksDev\Materials\Category\Entity\CategoryMaterial;
use BaksDev\Materials\Category\Entity\Info\CategoryMaterialInfo;
use BaksDev\Materials\Category\Entity\Offers\CategoryMaterialOffers;
use BaksDev\Materials\Category\Entity\Offers\Variation\CategoryMaterialVariation;
use BaksDev\Materials\Category\Entity\Offers\Variation\Modification\CategoryMaterialModification;
use BaksDev\Materials\Category\Entity\Trans\CategoryMaterialTrans;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Entity\Modify\MaterialSignModify;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\User\Repository\UserTokenStorage\UserTokenStorageInterface;
use BaksDev\Users\User\Type\Id\UserUid;

final class AllMaterialSignPartRepository implements AllMaterialSignPartInterface
{
    private PaginatorInterface $paginator;

    private DBALQueryBuilder $DBALQueryBuilder;

    private ?SearchDTO $search = null;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
        PaginatorInterface $paginator,
        private readonly UserTokenStorageInterface $userTokenStorage
    )
    {
        $this->paginator = $paginator;
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    /** Метод возвращает пагинатор MaterialSign */
    public function findPaginator(MaterialSignUid $part): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();


        $dbal
            ->select('invariable.number')
            ->from(
                MaterialSignInvariable::class, 'invariable'
            );

        $dbal
            ->andWhere('invariable.part = :part')
            ->setParameter('part', $part, MaterialSignUid::TYPE);

        $dbal
            ->andWhere('invariable.usr = :usr')
            ->setParameter('usr', $this->userTokenStorage->getUser(), UserUid::TYPE);


        $dbal
            ->addSelect('main.id AS sign_id')
            ->addSelect('main.event AS sign_event')
            ->join(
                'invariable',
                MaterialSign::class,
                'main',
                'main.id = invariable.main'
            );

        $dbal
            ->addSelect('event.status AS sign_status')
            ->addSelect('event.comment AS sign_comment')
            ->leftJoin(
                'code',
                MaterialSignEvent::class,
                'event',
                'event.id = main.event'
            );

        $dbal
            ->addSelect('code.code AS sign_code')
            ->addSelect("CASE
			    WHEN code.name IS NOT NULL THEN
					CONCAT ( '/upload/".$dbal->table(MaterialSignCode::class)."' , '/', code.name)
                   ELSE NULL
                END AS code_image")
            ->addSelect('code.ext AS code_ext')
            ->addSelect('code.cdn AS code_cdn')
            ->leftJoin(
                'main',
                MaterialSignCode::class,
                'code',
                'code.main = main.id'
            );

        $dbal
            ->addSelect('modify.mod_date AS sign_date')
            ->leftJoin(
                'code',
                MaterialSignModify::class,
                'modify',
                'modify.event = code.event'
            );


        // Material
        $dbal->addSelect('material.id as material_id'); //->addGroupBy('material.id');
        $dbal->addSelect('material.event as material_event'); //->addGroupBy('material.event');
        $dbal->join(
            'invariable',
            Material::class,
            'material',
            'material.id = invariable.material'
        );


        $dbal->join(
            'material',
            MaterialEvent::class,
            'material_event',
            'material_event.id = material.event'
        );

        $dbal
            ->leftJoin(
                'material_event',
                MaterialInfo::class,
                'material_info',
                'material_info.material = material.id'
            );


        $dbal
            ->addSelect('material_trans.name as material_name')
            ->join(
                'material_event',
                MaterialTrans::class,
                'material_trans',
                'material_trans.event = material_event.id AND material_trans.local = :local'
            );


        /**
         * Торговое предложение
         */

        $dbal
            ->addSelect('material_offer.id as material_offer_uid')
            ->addSelect('material_offer.value as material_offer_value')
            ->leftJoin(
                'material_event',
                MaterialOffer::class,
                'material_offer',
                'material_offer.event = material_event.id AND material_offer.const = invariable.offer'
            );


        //        if($this->filter?->getOffer())
        //        {
        //            $dbal->andWhere('material_offer.value = :offer');
        //            $dbal->setParameter('offer', $this->filter->getOffer());
        //        }


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

        $dbal->addSelect('material_variation.id as material_variation_uid')
            ->addSelect('material_variation.value as material_variation_value')
            ->leftJoin(
                'material_offer',
                MaterialVariation::class,
                'material_variation',
                'material_variation.offer = material_offer.id AND material_variation.const = invariable.variation'
            );

        //        if($this->filter?->getVariation())
        //        {
        //            $dbal->andWhere('material_variation.value = :variation');
        //            $dbal->setParameter('variation', $this->filter->getVariation());
        //        }

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
            ->addSelect('material_modification.id as material_modification_uid')
            ->addSelect('material_modification.value as material_modification_value')
            ->leftJoin(
                'material_variation',
                MaterialModification::class,
                'material_modification',
                'material_modification.variation = material_variation.id AND material_modification.const = invariable.modification'
            );

        //        if($this->filter?->getModification())
        //        {
        //            $dbal->andWhere('material_modification.value = :modification');
        //            $dbal->setParameter('modification', $this->filter->getModification());
        //        }

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

        $dbal->addSelect('
            COALESCE(
                material_modification.article, 
                material_variation.article, 
                material_offer.article, 
                material_info.article
            ) AS material_article
		');


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
                material_photo.event = material_event.id AND
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
            "
			CASE
			
			   WHEN material_modification_image.name IS NOT NULL THEN  material_modification_image.ext
			   WHEN material_variation_image.name IS NOT NULL THEN material_variation_image.ext
			   WHEN material_offer_images.name IS NOT NULL THEN material_offer_images.ext
			   WHEN material_photo.name IS NOT NULL THEN material_photo.ext
			   ELSE NULL
			   
			END AS material_image_ext
		"
        );

        // Флаг загрузки файла CDN
        $dbal->addSelect(
            '
			CASE
			   WHEN material_variation_image.name IS NOT NULL THEN
					material_variation_image.cdn
			   WHEN material_offer_images.name IS NOT NULL THEN
					material_offer_images.cdn
			   WHEN material_photo.name IS NOT NULL THEN
					material_photo.cdn
			   ELSE NULL
			END AS material_image_cdn
		'
        );

        // Категория
        $dbal->leftJoin(
            'material_event',
            MaterialCategory::class,
            'material_event_category',
            'material_event_category.event = material_event.id AND material_event_category.root = true'
        );


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

        /** Ответственное лицо */

        $dbal
            ->leftJoin(
                'event',
                UserProfile::class,
                'users_profile',
                'users_profile.id = event.profile'
            );


        $dbal
            ->addSelect('users_profile_personal.username AS users_profile_username')
            ->addSelect('users_profile_personal.location AS users_profile_location')
            ->leftJoin(
                'users_profile',
                UserProfilePersonal::class,
                'users_profile_personal',
                'users_profile_personal.event = users_profile.event'
            );

        /* Поиск */
        if($this->search?->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search)
                //->addSearchEqualUid('account.id')

                ->addSearchLike('code.code');
        }

        $dbal->orderBy('modify.mod_date', 'DESC');


        //dd(current($dbal->fetchAllAssociative()));

        return $this->paginator->fetchAllAssociative($dbal);
    }


}

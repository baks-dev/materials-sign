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

namespace BaksDev\Materials\Sign\Forms\MaterialSignTransfer;

use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Category\Type\Id\CategoryMaterialUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

final class MaterialSignTransferDTO
{
    /**
     * Владелец
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private ?UserProfileUid $profile = null;


    /**
     * Продавец
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private ?UserProfileUid $seller = null;


    /** Категория */
    #[Assert\Uuid]
    private ?CategoryMaterialUid $category = null;

    /** ID сырья */
    #[Assert\Uuid]
    private ?MaterialUid $material = null;

    /** Постоянный уникальный идентификатор ТП */
    #[Assert\Uuid]
    private ?MaterialOfferConst $offer = null;

    /** Постоянный уникальный идентификатор варианта */
    #[Assert\Uuid]
    private ?MaterialVariationConst $variation = null;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\Uuid]
    private ?MaterialModificationConst $modification = null;


    private ?DateTimeImmutable $from = null;

    private ?DateTimeImmutable $to = null;

    /**
     * Category
     */
    public function getCategory(): ?CategoryMaterialUid
    {
        return $this->category;
    }

    public function setCategory(?CategoryMaterialUid $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Material
     */
    public function getMaterial(): ?MaterialUid
    {
        return $this->material;
    }

    public function setMaterial(?MaterialUid $material): self
    {
        $this->material = $material;
        return $this;
    }

    /**
     * Offer
     */
    public function getOffer(): ?MaterialOfferConst
    {
        return $this->offer;
    }

    public function setOffer(?MaterialOfferConst $offer): self
    {
        $this->offer = $offer;
        return $this;
    }

    /**
     * Variation
     */
    public function getVariation(): ?MaterialVariationConst
    {
        return $this->variation;
    }

    public function setVariation(?MaterialVariationConst $variation): self
    {
        $this->variation = $variation;
        return $this;
    }

    /**
     * Modification
     */
    public function getModification(): ?MaterialModificationConst
    {
        return $this->modification;
    }

    public function setModification(?MaterialModificationConst $modification): self
    {
        $this->modification = $modification;
        return $this;
    }

    /**
     * Profile
     */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(?UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * From
     */
    public function getFrom(): DateTimeImmutable
    {
        return $this->from ?: new DateTimeImmutable('now');
    }

    public function setFrom(DateTimeImmutable|string|null $from): self
    {
        if(is_string($from))
        {
            $from = new DateTimeImmutable($from);
        }

        $this->from = $from;
        return $this;
    }

    /**
     * To
     */
    public function getTo(): DateTimeImmutable
    {
        return $this->to ?: new DateTimeImmutable('now');
    }

    public function setTo(DateTimeImmutable|string|null $to): self
    {
        if(is_string($to))
        {
            $to = new DateTimeImmutable($to);
        }

        $this->to = $to;

        return $this;
    }

    /**
     * Seller
     */
    public function getSeller(): ?UserProfileUid
    {
        return $this->seller;
    }

    public function setSeller(?UserProfileUid $seller): self
    {
        $this->seller = $seller;
        return $this;
    }

}

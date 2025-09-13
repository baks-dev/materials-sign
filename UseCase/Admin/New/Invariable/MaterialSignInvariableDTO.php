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

namespace BaksDev\Materials\Sign\UseCase\Admin\New\Invariable;

use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariableInterface;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignInvariable */
final class MaterialSignInvariableDTO implements MaterialSignInvariableInterface
{
    /** Пользователь */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserUid $usr;

    /**
     * Владелец честного пользователя
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserProfileUid $profile;

    /**
     * Продавец честного пользователя
     */
    private ?UserProfileUid $seller = null;


    /** Группа штрихкодов, для отмены  */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private string $part;

    /** Грузовая таможенная декларация (номер) */
    private ?string $number = null;

    // Продукция

    /** ID сырья */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private MaterialUid $material;

    /** Постоянный уникальный идентификатор ТП */
    private ?MaterialOfferConst $offer;

    /** Постоянный уникальный идентификатор варианта */
    private ?MaterialVariationConst $variation;

    /** Постоянный уникальный идентификатор модификации */
    private ?MaterialModificationConst $modification;

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }

    public function setUsr(UserUid $usr): self
    {
        $this->usr = $usr;
        return $this;
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    public function setProfile(UserProfileUid $profile): self
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * Group
     */
    public function getPart(): string
    {
        return $this->part;
    }

    public function setPart(mixed $part): self
    {
        $this->part = (string) $part;
        return $this;
    }

    /**
     * Material
     */
    public function getMaterial(): MaterialUid
    {
        return $this->material;
    }

    public function setMaterial(MaterialUid $material): self
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
     * Number
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): self
    {
        $this->number = $number;
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

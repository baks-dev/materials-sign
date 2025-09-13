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

namespace BaksDev\Materials\Sign\Messenger\MaterialSignPdf\MaterialSignScaner;

use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Type\Id\UserUid;

final class MaterialSignScannerMessage
{

    #[Assert\NotBlank]
    #[Assert\Uuid]
    private string $usr;

    #[Assert\Uuid]
    private ?string $profile;

    /** ID продукта */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly string $Material;

    /** Постоянный уникальный идентификатор ТП */
    #[Assert\Uuid]
    private readonly ?string $offer;

    /** Постоянный уникальный идентификатор варианта */
    #[Assert\Uuid]
    private readonly ?string $variation;

    /** Постоянный уникальный идентификатор модификации */
    #[Assert\Uuid]
    private readonly ?string $modification;

    /** Доступен только владельцу */
    private bool $share;

    /** Грузовая таможенная декларация (номер) */
    private ?string $number;

    public function __construct(
        private readonly string $path,
        private readonly mixed $part,
        UserUid $usr,
        ?UserProfileUid $profile,

        MaterialUid $material,
        ?MaterialOfferConst $offer,
        ?MaterialVariationConst $variation,
        ?MaterialModificationConst $modification,

        bool $share,
        ?string $number
    )
    {
        $this->usr = (string) $usr;
        $this->profile = (string) $profile;
        $this->Material = (string) $material;

        $this->offer = $offer ? (string) $offer : null;
        $this->variation = $variation ? (string) $variation : null;
        $this->modification = $modification ? (string) $modification : null;

        $this->share = $share;
        $this->number = $number;
    }

    /**
     * Usr
     */
    public function getUsr(): UserUid
    {
        return new UserUid($this->usr);
    }

    /**
     * Profile
     */
    public function getProfile(): ?UserProfileUid
    {
        return $this->profile ? new UserProfileUid($this->profile) : null;
    }

    /**
     * Material
     */
    public function getMaterial(): MaterialUid
    {
        return new MaterialUid($this->Material);
    }

    /**
     * Offer
     */
    public function getOffer(): ?MaterialOfferConst
    {
        return $this->offer ? new MaterialOfferConst($this->offer) : null;
    }

    /**
     * Variation
     */
    public function getVariation(): ?MaterialVariationConst
    {
        return $this->variation ? new MaterialVariationConst($this->variation) : null;
    }

    /**
     * Modification
     */
    public function getModification(): ?MaterialModificationConst
    {
        return $this->modification ? new MaterialModificationConst($this->modification) : null;
    }

    /**
     * Number
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    /**
     * Share
     */
    public function isNotShare(): bool
    {
        return $this->share;
    }


    public function getRealPath(): string
    {
        return $this->path;
    }

    public function getPart(): string
    {
        return (string) $this->part;
    }
}
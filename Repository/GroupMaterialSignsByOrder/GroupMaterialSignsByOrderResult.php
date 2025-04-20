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

namespace BaksDev\Materials\Sign\Repository\GroupMaterialSignsByOrder;

use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;

final readonly class GroupMaterialSignsByOrderResult
{

    public function __construct(
        private int $counter, // " => 100
        private string $sign_part, // " => "019643c2-9025-7050-b731-95ce5a4cbc77"

        private string $material_id, // " => "0194d10e-d18b-7ee9-9bbb-0ba83582342c"
        private string $material_name, // " => "Футболка короткий рукав"

        private ?string $material_offer_const, // " => "0194d10e-ce65-7dd2-97a2-0608f2683361"
        private ?string $material_offer_value, // " => "FFFFFF"
        private ?string $material_offer_reference, // " => "color_type"

        private ?string $material_variation_const, // " => "0194d10e-cf53-77e1-90dc-8342dd45b543"
        private ?string $material_variation_value, // " => "3XL"
        private ?string $material_variation_reference, // " => "size_clothing_type"

        private ?string $material_modification_const, // " => null
        private ?string $material_modification_value, // " => null
        private ?string $material_modification_reference, // " => null

        private string $material_article, // " => "T-WHITE-3XL"
    ) {}

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function getSignPart(): MaterialSignUid
    {
        return new MaterialSignUid($this->sign_part);
    }

    public function getMaterialId(): MaterialUid
    {
        return new MaterialUid($this->material_id);
    }


    public function getMaterialName(): string
    {
        return $this->material_name;
    }

    public function getMaterialArticle(): string
    {
        return $this->material_article;
    }

    /**
     * Offer
     */

    public function getMaterialOfferConst(): ?MaterialOfferConst
    {
        return $this->material_offer_const ? new MaterialOfferConst($this->material_offer_const) : null;
    }

    public function getMaterialOfferValue(): ?string
    {
        return $this->material_offer_value ?: null;
    }

    public function getMaterialOfferReference(): ?string
    {
        return $this->material_offer_reference ?: null;
    }

    /**
     * Variation
     */

    public function getMaterialVariationConst(): ?MaterialVariationConst
    {
        return $this->material_variation_const ? new MaterialVariationConst($this->material_variation_const) : null;
    }

    public function getMaterialVariationValue(): ?string
    {
        return $this->material_variation_value ?: null;
    }

    public function getMaterialVariationReference(): ?string
    {
        return $this->material_variation_reference ?: null;
    }

    /**
     * Modification
     */

    public function getMaterialModificationConst(): ?MaterialModificationConst
    {
        return $this->material_modification_const ? new MaterialModificationConst($this->material_modification_const) : null;
    }

    public function getMaterialModificationValue(): ?string
    {
        return $this->material_modification_value ?: null;
    }

    public function getMaterialModificationReference(): ?string
    {
        return $this->material_modification_reference ?: null;
    }
}
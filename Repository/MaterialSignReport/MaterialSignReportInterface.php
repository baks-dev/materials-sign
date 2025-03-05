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

namespace BaksDev\Materials\Sign\Repository\MaterialSignReport;

use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Category\Type\Id\CategoryMaterialUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;

interface MaterialSignReportInterface
{
    public function fromProfile(UserProfileUid|string $profile): self;

    public function fromSeller(UserProfileUid|string $seller): self;

    public function dateFrom(DateTimeImmutable $from): self;

    public function dateTo(DateTimeImmutable $to): self;

    public function setCategory(CategoryMaterialUid|string|null|false $category): self;

    /**
     * Material
     */

    public function setMaterial(MaterialUid|string|null|false $material): self;

    public function setOffer(MaterialOfferConst|string|null|false $offer): self;

    public function setVariation(MaterialVariationConst|string|null|false $variation): self;

    public function setModification(MaterialModificationConst|string|null|false $modification): self;

    /**
     * Метод получает все реализованные честные знаки
     */
    public function findAll(): array|false;
}
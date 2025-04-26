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

use DateTimeImmutable;

final readonly class MaterialSignReportResult
{
    public function __construct(

        private string $date,

        private string $code,
        private string $total,
        private string $material_name,

        private ?string $material_offer_value,
        private ?string $material_offer_reference,

        private ?string $material_variation_value,
        private ?string $material_variation_reference,

        private ?string $material_modification_value,
        private ?string $material_modification_reference,

    ) {}

    public function getDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->date);
    }


    public function getCode(): string
    {
        return $this->code;
    }

    public function getMaterialName(): string
    {
        return $this->material_name;
    }

    /**
     * Offer
     */

    public function getMaterialOfferValue(): ?string
    {
        return $this->material_offer_value;
    }

    public function getMaterialOfferReference(): ?string
    {
        return $this->material_offer_reference;
    }

    /**
     * Variation
     */

    public function getMaterialVariationValue(): ?string
    {
        return $this->material_variation_value;
    }

    public function getMaterialVariationReference(): ?string
    {
        return $this->material_variation_reference;
    }

    /**
     * Modification
     */

    public function getMaterialModificationValue(): ?string
    {
        return $this->material_modification_value;
    }

    public function getMaterialModificationReference(): ?string
    {
        return $this->material_modification_reference;
    }


    public function getInn(): int
    {
        return 5047263117;
    }

    public function getKpp(): int
    {
        return 504701001;
    }


}
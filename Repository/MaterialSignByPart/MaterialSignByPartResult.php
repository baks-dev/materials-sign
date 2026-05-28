<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

use BaksDev\Materials\Catalog\Type\Event\MaterialEventUid;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventUid;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignByPartResult */
final  class MaterialSignByPartResult
{
    private ?array $decode = null;

    private ?string $render = null;

    public function __construct(

        private readonly string $material_id,
        private readonly string $material_event,

        private readonly ?string $material_offer_value,
        private readonly ?string $material_offer_reference,

        private readonly ?string $material_variation_value,
        private readonly ?string $material_variation_reference,

        private readonly ?string $material_modification_value,
        private readonly ?string $material_modification_reference,

        private readonly string $material_name,

        private readonly string $sign_id,
        private readonly string $sign_event,
        private readonly string $code_image,
        private readonly string $code_ext,
        private readonly string $code_event,
        private readonly string $code_string,
        private readonly bool $code_cdn,
    ) {}

    public function getSignId(): MaterialSignUid
    {
        return new MaterialSignUid($this->sign_id);
    }

    public function getSignEvent(): MaterialSignEventUid
    {
        return new MaterialSignEventUid($this->sign_event);
    }

    public function getCodeImage(): string
    {
        return $this->code_image;
    }

    public function getCodeExt(): string
    {
        return $this->code_ext;
    }

    public function getCodeEvent(): MaterialSignEventUid
    {
        return new MaterialSignEventUid($this->code_event);
    }

    public function getCodeString(): string
    {
        return $this->code_string;
    }

    public function getSmallCode(): string
    {
        if(empty($this->decode))
        {
            preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code_string, $matches, PREG_SET_ORDER);
            $this->decode = $matches;
        }

        return
            $this->decode[0][1]
            .$this->decode[0][2]
            .$this->decode[1][1]
            .$this->decode[1][2];
    }


    public function getBigCode(): string
    {
        if(empty($this->decode))
        {
            preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code_string, $matches, PREG_SET_ORDER);
            $this->decode = $matches;
        }

        $subChar = "";

        return
            $this->decode[0][1]
            .$this->decode[0][2]
            .$this->decode[1][1]
            .$this->decode[1][2]
            .$subChar
            .$this->decode[2][1]
            .$this->decode[2][2]
            .$subChar
            .$this->decode[3][1]
            .$this->decode[3][2];
    }

    public function getMatrixGtin(): string
    {
        if(empty($this->decode))
        {
            preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code_string, $matches, PREG_SET_ORDER);
            $this->decode = $matches;
        }

        return $this->decode[0][2];
    }

    public function getMatrixCode(): string
    {
        if(empty($this->decode))
        {
            preg_match_all('/\((\d{2})\)((?:(?!\(\d{2}\)).)*)/', $this->code_string, $matches, PREG_SET_ORDER);
            $this->decode = $matches;
        }

        return $this->decode[1][2];
    }


    public function getCodeCdn(): bool
    {
        return $this->code_cdn === true;
    }

    public function getMaterialId(): MaterialUid
    {
        return new MaterialUid($this->material_id);
    }

    public function getMaterialEvent(): MaterialEventUid
    {
        return new MaterialEventUid($this->material_event);
    }

    public function getMaterialOfferValue(): ?string
    {
        return $this->material_offer_value;
    }

    public function getMaterialOfferReference(): ?string
    {
        return $this->material_offer_reference;
    }

    public function getMaterialVariationValue(): ?string
    {
        return $this->material_variation_value;
    }

    public function getMaterialVariationReference(): ?string
    {
        return $this->material_variation_reference;
    }

    public function getMaterialModificationValue(): ?string
    {
        return $this->material_modification_value;
    }

    public function getMaterialModificationReference(): ?string
    {
        return $this->material_modification_reference;
    }

    public function getMaterialName(): string
    {
        return $this->material_name;
    }

    public function getRender(): ?string
    {
        return $this->render;
    }

    public function setRender(?string $render): self
    {
        $this->render = $render;
        return $this;
    }
}
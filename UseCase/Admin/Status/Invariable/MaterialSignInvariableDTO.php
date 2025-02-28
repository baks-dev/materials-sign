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

namespace BaksDev\Materials\Sign\UseCase\Admin\Status\Invariable;

use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariableInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignInvariable */
final class MaterialSignInvariableDTO implements MaterialSignInvariableInterface
{
    /** Группа штрихкодов (для групповой отмены либо списания) */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private readonly MaterialSignUid $part;


    /**
     * Продавец честного пользователя
     */
    #[Assert\Uuid]
    private readonly ?UserProfileUid $seller;

    /**
     * Part
     */
    public function getPart(): MaterialSignUid
    {
        return $this->part;
    }

    public function setPart(MaterialSignUid $part): self
    {
        if(false === (new ReflectionProperty(self::class, 'part')->isInitialized($this)))
        {
            $this->part = $part;
        }

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
        if(false === (new ReflectionProperty(self::class, 'part')->isInitialized($this)))
        {
            $this->seller = $seller;
        }

        return $this;
    }
}

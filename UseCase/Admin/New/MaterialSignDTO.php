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

namespace BaksDev\Materials\Sign\UseCase\Admin\New;

use BaksDev\Materials\Sign\Entity\Event\MaterialSignEventInterface;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventUid;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\Collection\MaterialSignStatusInterface;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusError;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignEvent */
final class MaterialSignDTO implements MaterialSignEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?MaterialSignEventUid $id = null;

    /**
     * Код честного знака
     */
    #[Assert\Valid]
    private Code\MaterialSignCodeDTO $code;


    /**
     * Статус
     */
    #[Assert\NotBlank]
    private MaterialSignStatus $status;


    //    /**
    //     * Профиль пользователя
    //     */
    //    #[Assert\Uuid]
    //    private ?UserProfileUid $profile = null;


    /**
     * Код честного знака
     */
    #[Assert\Valid]
    private Invariable\MaterialSignInvariableDTO $invariable;


    public function __construct()
    {
        $this->status = new MaterialSignStatus(MaterialSignStatusNew::class);
        $this->code = new Code\MaterialSignCodeDTO();
        $this->invariable = new Invariable\MaterialSignInvariableDTO();
    }

    /**
     * Идентификатор события
     */
    public function getEvent(): ?MaterialSignEventUid
    {
        return $this->id;
    }

    public function setId(MaterialSignEventUid $id): void
    {
        $this->id = $id;
    }

    /**
     * Status
     */
    public function getStatus(): MaterialSignStatus
    {
        if($this->status->equals(MaterialSignStatusError::class))
        {
            $this->invariable->setPart(new MaterialSignUid());
        }

        return $this->status;
    }

    public function setStatus(MaterialSignStatus|MaterialSignStatusInterface|string $status): self
    {
        if(!$status instanceof MaterialSignStatus)
        {
            $status = new MaterialSignStatus($status);
        }

        $this->status = $status;

        return $this;
    }

    /**
     * Code
     */
    public function getCode(): Code\MaterialSignCodeDTO
    {
        return $this->code;
    }

    public function setCode(Code\MaterialSignCodeDTO $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\MaterialSignInvariableDTO
    {
        return $this->invariable;
    }

    public function setInvariable(Invariable\MaterialSignInvariableDTO $invariable): self
    {
        $this->invariable = $invariable;
        return $this;
    }


    //    /**
    //     * Profile
    //     */
    //    public function setProfile(?UserProfileUid $profile): self
    //    {
    //        $this->profile = $profile;
    //        return $this;
    //    }

    //    public function getProfile(): ?UserProfileUid
    //    {
    //        return $this->profile;
    //    }
}

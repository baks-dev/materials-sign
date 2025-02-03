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

namespace BaksDev\Materials\Sign\UseCase\Admin\Status;

use BaksDev\Materials\Sign\Entity\Event\MaterialSignEventInterface;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDecommission;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignEvent */
final readonly class MaterialSignDecommissionDTO implements MaterialSignEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private MaterialSignEventUid $id;

    /**
     * Статус
     */
    #[Assert\NotBlank]
    private MaterialSignStatus $status;

    /**
     * Профиль пользователя (null - общий)
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private UserProfileUid $profile;


    #[Assert\Valid]
    private Invariable\MaterialSignInvariableDTO $invariable;


    public function __construct(UserProfileUid $profile)
    {
        /** Статус Off «Списание» */
        $this->status = new MaterialSignStatus(MaterialSignStatusDecommission::class);
        $this->invariable = new Invariable\MaterialSignInvariableDTO();
        $this->profile = $profile;
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
     * Статус
     */
    public function getStatus(): MaterialSignStatus
    {
        return $this->status;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\MaterialSignInvariableDTO
    {
        return $this->invariable;
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }
}

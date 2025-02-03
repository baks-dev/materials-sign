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

use BaksDev\Materials\Catalog\Type\Id\MaterialUid;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEventInterface;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusProcess;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Orders\Order\Type\Material\OrderMaterialUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see MaterialSignEvent */
final readonly class MaterialSignProcessDTO implements MaterialSignEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    #[Assert\NotBlank]
    private MaterialSignEventUid $id;

    /**
     * Профиль пользователя
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    private UserProfileUid $profile;

    /**
     * Статус
     */
    #[Assert\NotBlank]
    private MaterialSignStatus $status;

    /**
     * Идентификатор заказа
     */
    #[Assert\NotBlank]
    private OrderUid $ord;

    #[Assert\Valid]
    private Invariable\MaterialSignInvariableDTO $invariable;


    public function __construct(UserProfileUid $profile, OrderUid $ord)
    {
        $this->profile = $profile;
        $this->ord = $ord;

        /** Статус Process «В процессе» */
        $this->status = new MaterialSignStatus(MaterialSignStatusProcess::class);
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
     * Статус
     */
    public function getStatus(): MaterialSignStatus
    {
        return $this->status;
    }

    /**
     * Профиль пользователя
     */
    public function getProfile(): UserProfileUid
    {
        return $this->profile;
    }

    /**
     * Идентификатор заказа
     */
    public function getOrd(): OrderUid
    {
        return $this->ord;
    }

    /**
     * Invariable
     */
    public function getInvariable(): Invariable\MaterialSignInvariableDTO
    {
        return $this->invariable;
    }

}

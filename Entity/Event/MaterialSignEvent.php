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

namespace BaksDev\Materials\Sign\Entity\Event;

use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Invariable\MaterialSignInvariable;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Entity\Modify\MaterialSignModify;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventUid;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* MaterialSignEvent */

#[ORM\Entity]
#[ORM\Table(name: 'material_sign_event')]
#[ORM\Index(columns: ['ord', 'status'])]
class MaterialSignEvent extends EntityEvent
{
    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: MaterialSignEventUid::TYPE)]
    private MaterialSignEventUid $id;

    /**
     * Идентификатор MaterialSign
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: MaterialSignUid::TYPE)]
    private ?MaterialSignUid $main = null;

    /**
     * Код честного знака
     */
    #[ORM\OneToOne(targetEntity: MaterialSignCode::class, mappedBy: 'event', cascade: ['all'], fetch: 'EAGER')]
    private ?MaterialSignCode $code = null;

    /**
     * Постоянная величина
     */
    #[ORM\OneToOne(targetEntity: MaterialSignInvariable::class, mappedBy: 'event', cascade: ['all'], fetch: 'EAGER')]
    private ?MaterialSignInvariable $invariable = null;

    /**
     * Модификатор
     */
    #[ORM\OneToOne(targetEntity: MaterialSignModify::class, mappedBy: 'event', cascade: ['all'], fetch: 'EAGER')]
    private MaterialSignModify $modify;

    /**
     * Статус
     */
    #[ORM\Column(type: MaterialSignStatus::TYPE)]
    private MaterialSignStatus $status;

    /**
     * Профиль пользователя (null - общий)
     * @deprecated переносится в MaterialSignInvariable
     * @see MaterialSignInvariable
     */
    #[ORM\Column(type: UserProfileUid::TYPE, nullable: true)]
    private ?UserProfileUid $profile = null;

    /**
     * ID заказа
     */
    #[ORM\Column(type: OrderUid::TYPE, nullable: true)]
    private ?OrderUid $ord = null;


    /** Комментарий */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string $comment;

    public function __construct()
    {
        $this->id = new MaterialSignEventUid();
        $this->modify = new MaterialSignModify($this);
    }

    /**
     * Идентификатор События
     */

    public function __clone()
    {
        $this->id = clone new MaterialSignEventUid();
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public function getId(): MaterialSignEventUid
    {
        return $this->id;
    }

    /**
     * Идентификатор MaterialSign
     */
    public function setMain(MaterialSignUid|MaterialSign|string $main): void
    {
        if(is_string($main))
        {
            $main = new MaterialSignUid($main);
        }

        $this->main = $main instanceof MaterialSign ? $main->getId() : $main;
    }


    public function getMain(): ?MaterialSignUid
    {
        return $this->main;
    }

    /**
     * Profile
     */
    public function getProfile(): UserProfileUid
    {
        return $this->invariable->getProfile();
    }

    /**
     * Status
     */
    public function getStatus(): MaterialSignStatus
    {
        return $this->status;
    }

    public function deleteCode(): void
    {
        $this->code->deleteCode();
    }


    public function getDto($dto): mixed
    {
        if($dto instanceof MaterialSignEventInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof MaterialSignEventInterface)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

}

<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Materials\Sign\Type\Status;

use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\Collection\MaterialSignStatusInterface;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;
use InvalidArgumentException;

final class MaterialSignStatus
{
    public const string TYPE = 'material_sign_status';

    public const string TEST = MaterialSignStatusNew::class;

    private MaterialSignStatusInterface $status;

    public function __construct(self|string|MaterialSignStatusInterface $status)
    {
        if(is_string($status) && class_exists($status))
        {
            $instance = new $status();

            if($instance instanceof MaterialSignStatusInterface)
            {
                $this->status = $instance;
                return;
            }
        }

        if($status instanceof MaterialSignStatusInterface)
        {
            $this->status = $status;
            return;
        }

        if($status instanceof self)
        {
            $this->status = $status->getMaterialSignStatus();
            return;
        }

        /** @var MaterialSignStatusInterface $declare */
        foreach(self::getDeclared() as $declare)
        {
            $instance = new self($declare);

            if($instance->getMaterialSignStatusValue() === $status)
            {
                $this->status = new $declare();
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('Not found OrderStatus %s', $status));

    }

    public function __toString(): string
    {
        return $this->status->getValue();
    }

    public function getMaterialSignStatus(): MaterialSignStatusInterface
    {
        return $this->status;
    }

    public function getMaterialSignStatusValue(): string
    {
        return $this->status->getValue();
    }


    public static function cases(): array
    {
        $case = [];

        foreach(self::getDeclared() as $declared)
        {
            /** @var MaterialSignStatusInterface $declared */
            $class = new $declared();

            $case[] = new self($class);
        }

        return $case;
    }

    public static function getDeclared(): array
    {
        return array_filter(
            get_declared_classes(),
            static function($className) {
                return in_array(MaterialSignStatusInterface::class, class_implements($className), true);
            }
        );
    }

    public function equals(mixed $status): bool
    {
        $status = new self($status);
        return $this->getMaterialSignStatusValue() === $status->getMaterialSignStatusValue();
    }

}

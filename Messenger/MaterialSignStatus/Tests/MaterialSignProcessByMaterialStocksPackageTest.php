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

namespace BaksDev\Materials\Sign\Messenger\MaterialSignStatus\Tests;

use BaksDev\Materials\Sign\Messenger\MaterialSignStatus\MaterialSignProcessByMaterialStocksPackageDispatcher;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Type\Event\ProductStockEventUid;
use BaksDev\Products\Stocks\Type\Id\ProductStockUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('materials-sign')]
class MaterialSignProcessByMaterialStocksPackageTest extends KernelTestCase
{

    public function testUseCase(): void
    {
        /** @var MaterialSignProcessByMaterialStocksPackageDispatcher $MaterialSignProcessByMaterialStocksPackage */
        $MaterialSignProcessByMaterialStocksPackage =
            self::getContainer()->get(MaterialSignProcessByMaterialStocksPackageDispatcher::class);

        $MaterialSignProcessByMaterialStocksPackage(
            new ProductStockMessage(
                new ProductStockUid('9900e5f5-5477-752b-b8c3-325b977bac92'),
                new ProductStockEventUid('6d3617d3-7b3f-725f-8fc8-0a26014137d3')
            )
        );

        self::assertTrue(true);
    }
}
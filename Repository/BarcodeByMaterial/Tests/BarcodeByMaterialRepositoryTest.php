<?php

declare(strict_types=1);

namespace BaksDev\Materials\Sign\Repository\BarcodeByMaterial\Tests;

use BaksDev\Materials\Sign\Repository\BarcodeByMaterial\BarcodeByMaterialInterface;
use BaksDev\Materials\Sign\Repository\BarcodeByMaterial\BarcodeByMaterialRepository;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('materials-sign')]
#[Group('materials-sign-repository')]
#[When(env: 'test')]
final class BarcodeByMaterialRepositoryTest extends KernelTestCase
{
    public function testFind(): void
    {
        $BarcodeByMaterialRepository = $this->getContainer()->get(BarcodeByMaterialInterface::class);

        /** @var BarcodeByMaterialRepository $BarcodeByMaterialRepository */
        $result = $BarcodeByMaterialRepository
            ->forMaterial(new MaterialUid(''))
            ->find();

//        dd($result);

        self::assertTrue(true);
    }
}
<?php

declare(strict_types=1);

namespace BaksDev\Materials\Sign\Repository\BarcodeByMaterial\Tests;

use BaksDev\Materials\Catalog\Type\Barcode\MaterialBarcode;
use BaksDev\Materials\Sign\Repository\BarcodeByMaterial\BarcodeByMaterialInterface;
use BaksDev\Materials\Sign\Repository\BarcodeByMaterial\BarcodeByMaterialRepository;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('materials-sign')]
#[Group('materials-sign-repository')]
#[When(env: 'test')]
final class BarcodeByMaterialRepositoryTest extends KernelTestCase
{
    public function testFind(): void
    {
        self::assertTrue(true);

        $BarcodeByMaterialRepository = self::getContainer()->get(BarcodeByMaterialInterface::class);

        /** @var BarcodeByMaterialRepository $BarcodeByMaterialRepository */
        $MaterialBarcode = $BarcodeByMaterialRepository
            ->forMaterial(new MaterialUid(''))
            ->find();

        if(false === $MaterialBarcode instanceof MaterialBarcode)
        {
            return;
        }

        // Вызываем все геттеры
        $reflectionClass = new ReflectionClass(MaterialBarcode::class);
        $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach($methods as $method)
        {
            // Методы без аргументов
            if($method->getNumberOfParameters() === 0)
            {
                // Вызываем метод
                $data = $method->invoke($MaterialBarcode);
                // dump($data);
            }
        }
    }
}
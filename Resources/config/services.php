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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Materials\Sign\BaksDevMaterialsSignBundle;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\Collection\MaterialSignStatusInterface;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusDone;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusNew;

return static function(ContainerConfigurator $container): void {

    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $NAMESPACE = BaksDevMaterialsSignBundle::NAMESPACE;
    $PATH = BaksDevMaterialsSignBundle::PATH;

    $services->load($NAMESPACE, $PATH)
        ->exclude([
            $PATH.'{Entity,Resources,Type}',
            $PATH.'**'.DIRECTORY_SEPARATOR.'*Message.php',
            $PATH.'**'.DIRECTORY_SEPARATOR.'*Result.php',
            $PATH.'**'.DIRECTORY_SEPARATOR.'*DTO.php',
            $PATH.'**'.DIRECTORY_SEPARATOR.'*Test.php',
        ]);


    /* Статусы заказов */
    $services->load(
        $NAMESPACE.'Type\Status\MaterialSignStatus\\',
        $PATH.implode(DIRECTORY_SEPARATOR, ['Type', 'Status', 'MaterialSignStatus']) //.'Type/Status/MaterialSignStatus'
    );

    /** @see https://symfony.com/doc/current/service_container/autowiring.html#dealing-with-multiple-implementations-of-the-same-type */

    $services->alias(MaterialSignStatusInterface::class.' $materialSignStatusNew', MaterialSignStatusNew::class);
    $services->alias(MaterialSignStatusInterface::class.' $materialSignStatusDone', MaterialSignStatusDone::class);

    $services->alias(MaterialSignStatusInterface::class, MaterialSignStatusNew::class);

};

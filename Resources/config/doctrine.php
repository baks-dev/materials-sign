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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Materials\Catalog\Type\Event\MaterialEventUid;
use BaksDev\Materials\Sign\BaksDevMaterialsSignBundle;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventType;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventUid;
use BaksDev\Materials\Sign\Type\Id\MaterialSignType;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatusType;
use Symfony\Config\DoctrineConfig;

return static function(ContainerConfigurator $container, DoctrineConfig $doctrine) {


    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /** Value Resolver */

    $services->set(MaterialSignUid::class)->class(MaterialSignUid::class);
    $services->set(MaterialSignEventUid::class)->class(MaterialSignEventUid::class);
    $services->set(MaterialSignStatus::class)->class(MaterialSignStatus::class);


    /* MaterialUid */

    $doctrine->dbal()->type(MaterialSignUid::TYPE)->class(MaterialSignType::class);
    $doctrine->dbal()->type(MaterialSignEventUid::TYPE)->class(MaterialSignEventType::class);
    $doctrine->dbal()->type(MaterialSignStatus::TYPE)->class(MaterialSignStatusType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);

    $emDefault->mapping('materials-sign')
        ->type('attribute')
        ->dir(BaksDevMaterialsSignBundle::PATH.'Entity')
        ->isBundle(false)
        ->prefix(BaksDevMaterialsSignBundle::NAMESPACE.'\\Entity')
        ->alias('materials-sign ');
};

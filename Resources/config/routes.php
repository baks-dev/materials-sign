<?php

use BaksDev\Materials\Sign\BaksDevMaterialsSignBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function(RoutingConfigurator $routes) {


    $MODULE = BaksDevMaterialsSignBundle::PATH;

    $routes->import(
        $MODULE.'Controller',
        'attribute',
        false,
        $MODULE.implode(DIRECTORY_SEPARATOR, ['Controller', '**', '*Test.php'])
    )
        ->prefix(\BaksDev\Core\Type\Locale\Locale::routes())
        ->namePrefix('materials-sign:');
};

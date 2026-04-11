<?php

use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/di-config.php');
$containerBuilder->useAutowiring(true);
$container = $containerBuilder->build();
return $container;

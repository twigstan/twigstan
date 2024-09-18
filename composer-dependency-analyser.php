<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

$config->ignoreErrorsOnPackageAndPaths(
    'symfony/framework-bundle',
    [
        __DIR__ . '/src/PHPStan/Collector/ContextFromRenderMethodCallCollector.php',
    ],
    [ErrorType::DEV_DEPENDENCY_IN_PROD],
);

$config->ignoreErrorsOnPackagesAndPaths(
    [
        'symfony/routing',
        'symfony/twig-bridge',
    ],
    [
        __DIR__ . '/src/PHPStan/Collector/ContextFromReturnedArrayWithTemplateAttributeCollector.php',
    ],
    [ErrorType::DEV_DEPENDENCY_IN_PROD],
);


return $config;

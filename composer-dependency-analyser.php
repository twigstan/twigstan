<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

$config->ignoreErrorsOnPackagesAndPaths(
    [
        'symfony/form',
        'symfony/framework-bundle',
    ],
    [
        __DIR__ . '/src/PHPStan/Collector/ContextFromControllerRenderMethodCallCollector.php',
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

// TODO: shipmonk-rnd/composer-dependency-analyser#202
$config->ignoreErrorsOnPackage(
    'symfony/polyfill-php84',
    [ErrorType::UNUSED_DEPENDENCY],
);

$config->addPathRegexToExclude('#\.twigstan/#');

return $config;

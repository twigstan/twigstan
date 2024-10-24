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

$config->addPathRegexToExclude('#\.twigstan/#');

// TODO: twig/twig:v3.15.0 Remove NameExpression check and bump minimum required Twig version to 3.15
$config->ignoreUnknownClasses([
    Twig\Node\Expression\Variable\ContextVariable::class,
]);

return $config;

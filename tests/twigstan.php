<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;
use TwigStan\EndToEnd\RenderPoints\CustomControllerContextCollector;
use TwigStan\Error\IgnoreError;

return TwigStanConfig::configure(__DIR__)
    ->phpstanBinPath(dirname(__DIR__) . '/vendor/bin/phpstan')
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php')
    ->twigContextCollector(CustomControllerContextCollector::class)
    ->ignoreErrors(IgnoreError::path('*/_*.twig'))
;

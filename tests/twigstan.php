<?php

declare(strict_types=1);

use Twig\Environment;
use TwigStan\Config\TwigStanConfig;
use TwigStan\EndToEnd\EndToEndContextCollector;
use TwigStan\EndToEnd\RenderPoints\CustomControllerContextCollector;
use TwigStan\Error\IgnoreError;

return TwigStanConfig::configure(__DIR__)
    ->phpstanBinPath(dirname(__DIR__) . '/vendor/bin/phpstan')
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php')
    ->twigContextCollector(
        CustomControllerContextCollector::class,
        EndToEndContextCollector::class,
    )
    ->ignoreErrors(IgnoreError::path('*/_*.twig'))
    ->twigExtensions('twig', sprintf('twig%d', Environment::MAJOR_VERSION))
    ->editorUrl('phpstorm://open?file=%file%&line=%line%')
;

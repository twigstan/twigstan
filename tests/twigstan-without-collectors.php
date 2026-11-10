<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

// Without Twig context collectors, whose files are always analysed when the render points are collected.
return TwigStanConfig::configure(__DIR__)
    ->phpstanBinPath(dirname(__DIR__) . '/vendor/bin/phpstan')
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php')
    ->phpPaths(__DIR__ . '/EndToEnd/OnlyWithContext')
    ->twigPaths(__DIR__ . '/EndToEnd/OnlyWithContext');

<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

return TwigStanConfig::configure(__DIR__)
    ->phpstanBinPath(dirname(__DIR__) . '/vendor/bin/phpstan')
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php');

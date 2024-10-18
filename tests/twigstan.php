<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

return TwigStanConfig::configure(__DIR__)
    ->tempDirectory(__DIR__ . '/.twigstan')
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php');

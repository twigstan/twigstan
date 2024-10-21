<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

return TwigStanConfig::configure(__DIR__)
    ->reportUnmatchedIgnoredErrors(true)
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->phpstanMemoryLimit(false)
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php')
    ->twigPaths(__DIR__ . '/templates')
    ->phpPaths(__DIR__ . '/src')
    ->create();

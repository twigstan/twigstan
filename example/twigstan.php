<?php

declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;
use TwigStan\Error\IgnoreError;

return TwigStanConfig::configure()
    // ->baselineFile(__DIR__ . '/twigstan-baseline.php')
    ->reportUnmatchedIgnoredErrors(true)
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->phpstanMemoryLimit(false)
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.untrack.php')
    ->twigPaths(__DIR__ . '/tests/EndToEnd/Types')
    ->twigExcludes('something.html.twig')
    ->phpPaths(__DIR__ . '/tests/Fixtures')
    ->phpExcludes('something.php')
    ->ignoreErrors(
        IgnoreError::create('#SomeOther#', 'someIdentifier.someValue', __DIR__ . '/some/path.php'),
        IgnoreError::message('#SomePattern#'),
        IgnoreError::identifier('someIdentifier'),
        IgnoreError::path(__DIR__ . '/some/path.php'),
        IgnoreError::messageAndIdentifier('#SomePattern#', 'someIdentifier'),
        IgnoreError::messageAndPath('#SomePattern#', __DIR__ . '/some/path.php'),
        IgnoreError::identifierAndPath('someIdentifier', __DIR__ . '/some/path.php'),
    )
    ->create();

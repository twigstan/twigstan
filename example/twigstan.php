<?php

declare(strict_types=1);

return TwigStan\Config\TwigStanConfig::configure(__DIR__)
    // ->baselineFile(__DIR__ . '/twigstan-baseline.php')
    ->reportUnmatchedIgnoredErrors(true)
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->phpstanMemoryLimit(false)
    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php')
    ->twigPaths(__DIR__ . '/tests/EndToEnd/Types')
    // ->twigExcludes('something.html.twig')
    ->phpPaths(__DIR__ . '/tests/Fixtures')
    // ->phpExcludes('something.php')
    // ->ignoreErrors(
    //     TwigStan\Error\IgnoreError::create('#SomeOther#', 'someIdentifier.someValue', __DIR__ . '/some/path.php'),
    //     TwigStan\Error\IgnoreError::message('#SomePattern#'),
    //     TwigStan\Error\IgnoreError::identifier('someIdentifier'),
    //     TwigStan\Error\IgnoreError::path(__DIR__ . '/some/path.php'),
    //     TwigStan\Error\IgnoreError::messageAndIdentifier('#SomePattern#', 'someIdentifier'),
    //     TwigStan\Error\IgnoreError::messageAndPath('#SomePattern#', __DIR__ . '/some/path.php'),
    //     TwigStan\Error\IgnoreError::identifierAndPath('someIdentifier', __DIR__ . '/some/path.php'),
    // )
    ->create();

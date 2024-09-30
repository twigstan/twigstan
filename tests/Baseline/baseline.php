<?php

declare(strict_types=1);

return [
    new TwigStan\Error\BaselineError(
        'If condition is always false.',
        'if.alwaysFalse',
        __DIR__ . '/homepage.html.twig',
        1,
    ),
    new TwigStan\Error\BaselineError(
        'Variable \'name\' does not exist.',
        'offsetAccess.notFound',
        __DIR__ . '/layout.html.twig',
        3,
    ),
    new TwigStan\Error\BaselineError(
        'Variable \'email\' does not exist.',
        'offsetAccess.notFound',
        __DIR__ . '/layout.html.twig',
        2,
    ),
    new TwigStan\Error\BaselineError(
        'Variable \'userId\' does not exist.',
        'offsetAccess.notFound',
        __DIR__ . '/layout.html.twig',
        1,
    ),
];

<?php

declare(strict_types=1);

use TwigStan\Error\BaselineError;

return [
    new BaselineError(
        'Casting to *NEVER* something that\'s already *NEVER*.',
        'cast.useless',
        __DIR__ . '/homepage.html.twig',
        1,
    ),
    new BaselineError(
        'Instanceof between *NEVER* and Twig\\Markup will always evaluate to false.',
        'instanceof.alwaysFalse',
        __DIR__ . '/homepage.html.twig',
        1,
    ),
    new BaselineError(
        'Left side of && is always false.',
        'booleanAnd.leftAlwaysFalse',
        __DIR__ . '/homepage.html.twig',
        1,
    ),
    new BaselineError(
        'Variable \'name\' does not exist.',
        'offsetAccess.notFound',
        __DIR__ . '/layout.html.twig',
        3,
    ),
    new BaselineError(
        'Variable \'email\' does not exist.',
        'offsetAccess.notFound',
        __DIR__ . '/layout.html.twig',
        2,
    ),
    new BaselineError(
        'Variable \'userId\' does not exist.',
        'offsetAccess.notFound',
        __DIR__ . '/layout.html.twig',
        1,
    ),
];

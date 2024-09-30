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
        'Undefined variable: name',
        'variable.undefined',
        __DIR__ . '/layout.html.twig',
        3,
    ),
    new TwigStan\Error\BaselineError(
        'Undefined variable: email',
        'variable.undefined',
        __DIR__ . '/layout.html.twig',
        2,
    ),
    new TwigStan\Error\BaselineError(
        'Undefined variable: userId',
        'variable.undefined',
        __DIR__ . '/layout.html.twig',
        1,
    ),
];

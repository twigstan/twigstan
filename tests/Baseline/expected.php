<?php

declare(strict_types=1);

use TwigStan\Error\BaselineError;

return [
    new BaselineError(
        'If condition is always false.',
        'if.alwaysFalse',
        '@__main__/Baseline/homepage.html.twig',
        1,
    ),
    new BaselineError(
        'Variable \'name\' does not exist.',
        'offsetAccess.notFound',
        '@__main__/Baseline/layout.html.twig',
        3,
    ),
    new BaselineError(
        'Variable \'email\' does not exist.',
        'offsetAccess.notFound',
        '@__main__/Baseline/layout.html.twig',
        2,
    ),
    new BaselineError(
        'Variable \'userId\' does not exist.',
        'offsetAccess.notFound',
        '@__main__/Baseline/layout.html.twig',
        1,
    ),
];

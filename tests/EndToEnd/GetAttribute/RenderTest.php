<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute;

use Override;
use TwigStan\EndToEnd\AbstractRenderingTestCase;
use TwigStan\EndToEnd\GetAttribute\Fixtures\Account;
use TwigStan\EndToEnd\GetAttribute\Fixtures\OrderStatus;
use TwigStan\EndToEnd\GetAttribute\Fixtures\State;
use TwigStan\EndToEnd\GetAttribute\Fixtures\User;

final class RenderTest extends AbstractRenderingTestCase
{
    #[Override]
    public static function getContextForTemplates(): iterable
    {
        yield 'array.render.twig' => [
            'template' => __DIR__ . '/array.render.twig',
            'context' => [
                'titles' => [
                    'Hello, World!',
                    'Bonjour, le monde!',
                ],
            ],
        ];

        yield 'call_on_null.render.twig' => [
            'template' => __DIR__ . '/call_on_null.render.twig',
            'context' => [
                'user' => new User(
                    'Ruud',
                    'ruud@localhost',
                    true,
                    true,
                    null,
                ),
            ],
        ];

        yield 'constants.render.twig' => [
            'template' => __DIR__ . '/constants.render.twig',
            'context' => [
                'state' => new State(),
            ],
        ];

        yield 'enums.render.twig' => [
            'template' => __DIR__ . '/enums.render.twig',
            'context' => [
                'status' => OrderStatus::Paid,
            ],
        ];

        yield 'method_calls.render.twig' => [
            'template' => __DIR__ . '/method_calls.render.twig',
            'context' => [
                'user' => new User(
                    'Ruud',
                    'ruud@localhost',
                    true,
                    true,
                    null,
                ),
            ],
        ];

        yield 'properties.render.twig' => [
            'template' => __DIR__ . '/properties.render.twig',
            'context' => [
                'account' => new Account('123', 'TwigStan'),
            ],
        ];
    }
}

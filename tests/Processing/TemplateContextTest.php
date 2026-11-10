<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TwigStan\Twig\SourceLocation;

final class TemplateContextTest extends TestCase
{
    #[Test]
    public function it_should_not_report_unchanged_context_for_a_recursive_include(): void
    {
        $template = 'category-navigation.html.twig';
        $sourceLocation = new SourceLocation($template, 1);
        $renderPoints = [
            $template => [
                $sourceLocation->getHash() => [$sourceLocation, 'array{level: int}'],
            ],
        ];

        $changedTemplates = [];
        $context = (new TemplateContext($renderPoints))->merge(new TemplateContext($renderPoints), $changedTemplates);

        self::assertSame($renderPoints, $context->context);
        self::assertSame([], $changedTemplates);
    }

    /**
     * The render points arrive in the order the parallel PHPStan workers finish, and their order shows in the merged context.
     */
    #[Test]
    public function it_should_order_render_points_that_share_their_first_location(): void
    {
        $fromController = new SourceLocation('page.twig', 3, new SourceLocation('Controller.php', 20));
        $fromCommand = new SourceLocation('page.twig', 3, new SourceLocation('Command.php', 10));
        $renderPoints = [
            $fromController->getHash() => [$fromController, 'array{flag: bool}'],
            $fromCommand->getHash() => [$fromCommand, 'array{flag: true}'],
        ];

        foreach ([$renderPoints, array_reverse($renderPoints, true)] as $arrival) {
            self::assertSame(
                ['array{flag: true}', 'array{flag: bool}'],
                (new TemplateContext(['partial.twig' => $arrival]))->getUniqueContextByTemplate('partial.twig'),
            );
        }
    }

    /**
     * @param array<string, string> $updates
     */
    #[DataProvider('provideContextUpdates')]
    #[Test]
    public function it_should_compare_all_context_types_after_merging(array $updates, bool $changed): void
    {
        $template = 'category-navigation.html.twig';
        $firstLocation = new SourceLocation($template, 1);
        $secondLocation = new SourceLocation($template, 2);
        $renderPoints = [
            'first' => [$firstLocation, 'array{level: int}'],
            'second' => [$secondLocation, 'array{level: string}'],
        ];
        $incoming = [];
        $expected = $renderPoints;
        foreach ($updates as $hash => $type) {
            $incoming[$hash] = [$renderPoints[$hash][0], $type];
            $expected[$hash] = $incoming[$hash];
        }

        $changedTemplates = [];
        $context = (new TemplateContext([$template => $renderPoints]))->merge(new TemplateContext([$template => $incoming]), $changedTemplates);

        self::assertSame([$template => $expected], $context->context);
        self::assertSame($changed ? [$template] : [], $changedTemplates);
    }

    /**
     * @return iterable<string, array{array<string, string>, bool}>
     */
    public static function provideContextUpdates(): iterable
    {
        yield 'unchanged render point preserves other contexts' => [
            ['first' => 'array{level: int}'], false,
        ];
        yield 'new context type requires analysis' => [
            ['first' => 'array{level: float}'], true,
        ];
        yield 'removed context type requires analysis' => [
            ['first' => 'array{level: string}'], true,
        ];
        yield 'reordered context types do not require analysis' => [
            ['first' => 'array{level: string}', 'second' => 'array{level: int}'], false,
        ];
    }

    #[Test]
    public function it_should_merge(): void
    {
        $left = new TemplateContext([
            'layout.twig' => [
                'hash1' => [new SourceLocation('HomepageController.php', 23), 'array{}'],
                'hash2' => [new SourceLocation('HomepageController.php', 34), 'array{name: string}'],
            ],
            'contact.twig' => [
                'hash3' => [new SourceLocation('ContactController.php', 2), 'array{name: string}'],
            ],
            'footer.twig' => [
                'hash4' => [new SourceLocation('FooterController.php', 45), 'array{title: string}'],
            ],
        ]);

        $right = new TemplateContext([
            'footer.twig' => [
                'hash5' => [new SourceLocation('layout.twig', 45), 'array{title: string}'],
            ],
            'contact.twig' => [
                'hash6' => [new SourceLocation('layout.twig', 22), 'array{name: string}'],
            ],
            'other.twig' => [
                'hash7' => [new SourceLocation('footer.twig', 45), 'array{title: string}'],
            ],
        ]);

        $changedTemplates = [];
        $context = $left->merge($right, $changedTemplates);

        self::assertEquals([
            'layout.twig' => [
                'hash1' => [new SourceLocation('HomepageController.php', 23), 'array{}'],
                'hash2' => [new SourceLocation('HomepageController.php', 34), 'array{name: string}'],
            ],
            'contact.twig' => [
                'hash3' => [new SourceLocation('ContactController.php', 2), 'array{name: string}'],
                'hash6' => [new SourceLocation('layout.twig', 22), 'array{name: string}'],
            ],
            'footer.twig' => [
                'hash4' => [new SourceLocation('FooterController.php', 45), 'array{title: string}'],
                'hash5' => [new SourceLocation('layout.twig', 45), 'array{title: string}'],
            ],
            'other.twig' => [
                'hash7' => [new SourceLocation('footer.twig', 45), 'array{title: string}'],
            ],
        ], $context->context);

        self::assertSame([
            'other.twig',
        ], $changedTemplates);
    }
}

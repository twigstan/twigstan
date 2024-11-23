<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TwigStan\Twig\SourceLocation;

final class TemplateContextTest extends TestCase
{
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

    #[Test]
    public function it_should_only_merge_when_source_location_of_right_side_is_render_point_in_left_side(): void
    {
        $left = new TemplateContext([
            'contact.twig' => [
                'hash1' => [new SourceLocation('ContactController.php', 2), 'array{name: string}'],
            ],
            'footer.twig' => [
                'hash4' => [new SourceLocation('FooterController.php', 45), 'array{title: string}'],
            ],
        ]);

        $right = new TemplateContext([
            'footer.twig' => [
                'hash2' => [new SourceLocation('contact.twig', 22), 'array{title: null|string}'],
                'hash3' => [new SourceLocation('layout.twig', 45), 'array{title: null|string}'],
            ],
            'header.twig' => [
                'hash5' => [new SourceLocation('contact.twig', 22), 'array{title: null|string}'],
                'hash6' => [new SourceLocation('layout.twig', 45), 'array{title: null|string}'],
            ],
        ]);

        $changedTemplates = [];
        $context = $left->merge($right, $changedTemplates);

        self::assertEquals([
            'contact.twig' => [
                'hash1' => [new SourceLocation('ContactController.php', 2), 'array{name: string}'],
            ],
            'footer.twig' => [
                'hash4' => [new SourceLocation('FooterController.php', 45), 'array{title: string}'],
                'hash2' => [new SourceLocation('contact.twig', 22), 'array{title: null|string}'],
            ],
            'header.twig' => [
                'hash5' => [new SourceLocation('contact.twig', 22), 'array{title: null|string}'],
            ],
        ], $context->context);

        self::assertSame([
            'footer.twig',
            'header.twig',
        ], $changedTemplates);
    }
}

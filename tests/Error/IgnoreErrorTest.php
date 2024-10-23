<?php

declare(strict_types=1);

namespace TwigStan\Error;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TwigStan\PHPStan\Analysis\Error;
use TwigStan\Twig\SourceLocation;

final class IgnoreErrorTest extends TestCase
{
    #[DataProvider('provideShouldIgnore')]
    #[DataProvider('provideShouldNotIgnore')]
    public function testShouldIgnore(bool $expected, IgnoreError $ignoreError, Error $error): void
    {
        self::assertSame($expected, $ignoreError->shouldIgnore($error));
    }

    /**
     * @return iterable<array{true, IgnoreError, Error}>
     */
    public static function provideShouldIgnore(): iterable
    {
        yield [
            true,
            IgnoreError::message('#message#'),
            new Error(
                'message',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'identifier',
                null,
            ),
        ];
        yield [
            true,
            IgnoreError::identifier('identifier'),
            new Error(
                'message',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'identifier',
                null,
            ),
        ];
        yield [
            true,
            IgnoreError::path('templates/layout.html.twig'),
            new Error(
                'message',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'identifier',
                null,
            ),
        ];
        yield [
            true,
            IgnoreError::path('*.html.twig'),
            new Error(
                'message',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'identifier',
                null,
            ),
        ];
        yield [
            true,
            IgnoreError::create('#message#', 'identifier', 'templates/layout.html.twig'),
            new Error(
                'message',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'identifier',
                null,
            ),
        ];
    }

    /**
     * @return iterable<array{false, IgnoreError, Error}>
     */
    public static function provideShouldNotIgnore(): iterable
    {
        yield [
            false,
            IgnoreError::message('#message#'),
            new Error(
                'someError',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'someIdentifier',
                null,
            ),
        ];
        yield [
            false,
            IgnoreError::identifier('identifier'),
            new Error(
                'someError',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'someIdentifier',
                null,
            ),
        ];
        yield [
            false,
            IgnoreError::path('templates/base.html.twig'),
            new Error(
                'someError',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'someIdentifier',
                null,
            ),
        ];
        yield [
            false,
            IgnoreError::path('templates/base.html.twig'),
            new Error(
                'someError',
                'generated.php',
                1,
                null,
                null,
                'someIdentifier',
                null,
            ),
        ];
        yield [
            false,
            IgnoreError::messageAndIdentifier('#message#', 'identifier'),
            new Error(
                'someError',
                'generated.php',
                1,
                'templates/layout.html.twig',
                new SourceLocation('templates/layout.html.twig', 1),
                'someIdentifier',
                null,
            ),
        ];
    }
}

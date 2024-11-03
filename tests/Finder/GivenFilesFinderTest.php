<?php

declare(strict_types=1);

namespace TwigStan\Finder;

use PHPUnit\Framework\TestCase;

final class GivenFilesFinderTest extends TestCase
{
    private GivenFilesFinder $givenFilesFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->givenFilesFinder = new GivenFilesFinder(
            __DIR__,
            ['php'],
            ['twig'],
        );
    }

    public function testFindFiles(): void
    {
        $files = $this->givenFilesFinder->find([
            'files/file.php',
            'files/template.twig',
        ]);

        self::assertSame([
            __DIR__ . '/files/file.php',
            __DIR__ . '/files/template.twig',
        ], array_keys($files));
    }

    public function testFindDirectory(): void
    {
        $files = $this->givenFilesFinder->find([
            'files',
        ]);

        self::assertSame([
            __DIR__ . '/files/exclude.php',
            __DIR__ . '/files/exclude.twig',
            __DIR__ . '/files/file.php',
            __DIR__ . '/files/template.twig',
        ], array_keys($files));
    }
}

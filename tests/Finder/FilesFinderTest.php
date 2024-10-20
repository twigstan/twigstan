<?php

declare(strict_types=1);

namespace TwigStan\Finder;

use PHPUnit\Framework\TestCase;

final class FilesFinderTest extends TestCase
{
    private FilesFinder $phpFileFinder;

    private FilesFinder $twigFileFinder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phpFileFinder = new FilesFinder(
            '*.php',
            [__DIR__ . '/files'],
            ['*/exclude.php'],
            __DIR__,
        );
        $this->twigFileFinder = new FilesFinder(
            '*.twig',
            [__DIR__ . '/files'],
            ['*/exclude.twig'],
            __DIR__,
        );
    }

    public function testFindPhpFiles(): void
    {
        $files = $this->phpFileFinder->find();

        self::assertSame([
            __DIR__ . '/files/file.php',
        ], array_keys($files));
    }

    public function testFindTwigFiles(): void
    {
        $files = $this->twigFileFinder->find();

        self::assertSame([
            __DIR__ . '/files/template.twig',
        ], array_keys($files));
    }

    public function testFindWithNoPaths(): void
    {
        $finder = new FilesFinder(
            '*.twig',
            [],
            [],
            __DIR__,
        );

        $files = $finder->find();

        self::assertSame([], array_keys($files));
    }
}

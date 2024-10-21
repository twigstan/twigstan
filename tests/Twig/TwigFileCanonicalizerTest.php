<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigFileCanonicalizerTest extends TestCase
{
    private TwigFileCanonicalizer $canonicalizer;
    private FilesystemLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new FilesystemLoader(
            __DIR__ . '/TwigFileNormalizerFixtures/templates',
            __DIR__ . '/TwigFileNormalizerFixtures/templates',
        );
        $this->loader->addPath(__DIR__ . '/TwigFileNormalizerFixtures/admin', 'Admin');
        $this->loader->addPath(__DIR__ . '/TwigFileNormalizerFixtures/templates/mail2', 'Mail');
        $this->loader->addPath(__DIR__ . '/TwigFileNormalizerFixtures/templates/mail', 'Mail');

        $this->canonicalizer = new TwigFileCanonicalizer(new Environment($this->loader));
    }

    #[DataProvider('provideCanonicalize')]
    public function testCanonicalize(string $fileName, string $expected): void
    {
        self::assertSame($expected, $this->canonicalizer->canonicalize($fileName));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function provideCanonicalize(): iterable
    {
        // Main namespace
        yield ['sub/add.html.twig', '@__main__/sub/add.html.twig'];
        yield ['sub\add.html.twig', '@__main__/sub/add.html.twig'];
        yield ['@__main__/sub/add.html.twig', '@__main__/sub/add.html.twig'];
        yield [__DIR__ . '/TwigFileNormalizerFixtures/templates/sub/add.html.twig', '@__main__/sub/add.html.twig'];

        // Admin namespace
        yield ['@Admin/layout.html.twig', '@Admin/layout.html.twig'];
        yield [__DIR__ . '/TwigFileNormalizerFixtures/admin/layout.html.twig', '@Admin/layout.html.twig'];

        // This happens to be also inside the root directory
        yield ['mail/layout.html.twig', '@__main__/mail/layout.html.twig'];

        // Therefore, @__main__ should always be returned, to make it canonical
        yield ['@Mail/layout.html.twig', '@__main__/mail/layout.html.twig'];
        yield ['@Mail/other.html.twig', '@__main__/mail2/other.html.twig'];
        yield [__DIR__ . '/TwigFileNormalizerFixtures/templates/mail/layout.html.twig', '@__main__/mail/layout.html.twig'];
        yield [__DIR__ . '/TwigFileNormalizerFixtures/templates/mail2/other.html.twig', '@__main__/mail2/other.html.twig'];
    }
}

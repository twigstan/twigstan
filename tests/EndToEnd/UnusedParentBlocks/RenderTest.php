<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\UnusedParentBlocks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class RenderTest extends TestCase
{
    #[DataProvider('templates')]
    public function testInheritedBlocksWithStrictVariables(string $template, string $expected): void
    {
        $loader = new FilesystemLoader(__DIR__);
        $loader->addPath(dirname(__DIR__), 'EndToEnd');
        $twig = new Environment($loader, ['strict_variables' => true]);

        self::assertSame($expected, trim($twig->render($template, ['message' => 'known'])));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function templates(): iterable
    {
        yield 'outer override leaves the inherited inner block unused' => ['child.twig', 'replacement'];
        yield 'parent call reaches the inherited inner block' => ['child_with_parent.twig', 'known'];
        yield 'direct parent render reaches the inner block' => ['layout.twig', 'known'];
    }
}

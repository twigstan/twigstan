<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\DynamicStringKeys;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class RenderTest extends TestCase
{
    #[DataProvider('templates')]
    public function testDynamicStringKeyWithStrictVariables(string $template, string $expected): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__), ['strict_variables' => true, 'autoescape' => false]);

        self::assertSame($expected, trim($twig->render($template)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function templates(): iterable
    {
        yield 'plain string' => ['plain.twig', '{"name":"value"}'];
        yield 'concatenated string' => ['concat.twig', '{"%name%":"value"}'];
    }
}

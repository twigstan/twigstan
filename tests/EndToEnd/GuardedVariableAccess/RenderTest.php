<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GuardedVariableAccess;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\FilesystemLoader;

final class RenderTest extends TestCase
{
    #[DataProvider('guardedTemplates')]
    public function testMissingInputIsAllowedWithStrictVariables(string $template): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__), ['strict_variables' => true]);

        self::assertSame("fallback\n", $twig->load($template)->render());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function guardedTemplates(): iterable
    {
        foreach (['direct', 'attribute', 'method'] as $access) {
            foreach (['is-defined', 'coalesce', 'default'] as $guard) {
                yield $access . '/' . $guard => [$access . '/' . $guard . '.twig'];
            }
        }
    }

    public function testUnguardedInputStillThrowsWithStrictVariables(): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__), ['strict_variables' => true]);

        $this->expectException(RuntimeError::class);

        $twig->load('unguarded.twig')->render();
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\NestedBlockCondition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class RenderTest extends TestCase
{
    #[DataProvider('templates')]
    public function testMessageIsAvailableWithStrictVariables(string $template, bool $enabled): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__), ['strict_variables' => true]);

        self::assertSame('present', trim($twig->load($template)->render(['enabled' => $enabled])));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function templates(): iterable
    {
        foreach (['nested', 'without_condition', 'without_inner', 'without_middle', 'derived_assignment'] as $template) {
            foreach ([false, true] as $enabled) {
                yield $template . ($enabled ? ' enabled' : ' disabled') => [$template . '.twig', $enabled];
            }
        }
    }
}

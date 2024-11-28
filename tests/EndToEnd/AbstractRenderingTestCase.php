<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use TwigStan\Twig\TokenParser\AssertTypeTokenParser;
use TwigStan\Twig\TokenParser\PrintAssertTypeTokenParser;

abstract class AbstractRenderingTestCase extends TestCase
{
    /**
     * @return iterable<string, array{template: string, context: array<mixed>}>
     */
    abstract public static function getContextForTemplates(): iterable;

    #[Test]
    public function ensureAllTemplatesAreDefined(): void
    {
        $templates = [];
        foreach (static::getContextForTemplates() as ['template' => $template]) {
            $templates[] = $template;
        }

        $filename = (new ReflectionClass($this))->getFileName();
        self::assertNotFalse($filename);

        $directory = Path::getDirectory($filename);
        $found = [];
        foreach (Finder::create()->files()->in($directory)->name('*.render.twig') as $file) {
            $found[] = $file->getRealPath();
        }

        sort($templates);
        sort($found);

        $diff = array_diff($found, $templates);

        self::assertSame([], $diff, sprintf(
            "The following templates are not defined in %s::getContextForTemplates():\n%s\n",
            $this::class,
            implode(PHP_EOL, $diff),
        ));

        $diff = array_diff($templates, $found);

        self::assertSame([], $diff, sprintf(
            "The following templates are not found in the test directory:\n%s\n",
            implode(PHP_EOL, $diff),
        ));
    }

    /**
     * @param array<mixed> $context
     */
    #[DataProvider('getContextForTemplates')]
    public function testRendering(string $template, array $context): void
    {
        $filesystem = new Filesystem();
        $templateBody = $filesystem->readFile($template);
        Assert::assertStringContainsString('---OUTPUT---', $templateBody, 'A render template must contain the "---OUTPUT---" separator.');

        [$twigTemplate, $expectedOutput] = explode('---OUTPUT---', $templateBody);

        $environment = new Environment(new ArrayLoader([
            'template.twig' => trim($twigTemplate),
        ]));
        $environment->disableStrictVariables();
        $environment->addTokenParser(new AssertTypeTokenParser(false));
        $environment->addTokenParser(new PrintAssertTypeTokenParser(false));

        $actualOutput = implode(
            "\n",
            array_map(
                trim(...),
                explode(
                    "\n",
                    trim($environment->render('template.twig', $context)),
                ),
            ),
        );

        self::assertSame(
            trim($expectedOutput),
            $actualOutput,
        );
    }
}

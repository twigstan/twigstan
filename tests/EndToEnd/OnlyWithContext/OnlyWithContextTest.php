<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\OnlyWithContext;

use Symfony\Component\Console\Output\BufferedOutput;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;
use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Only reports errors in templates that are rendered with a known context.
 */
final class OnlyWithContextTest extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-with-context.php';
    }

    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }

    /**
     * When only templates are given, their render points are collected from the configured PHP paths.
     */
    public function testOnlyTemplatePaths(): void
    {
        $command = ContainerFactory::fromFile(dirname(__DIR__, 3), __DIR__ . '/../../twigstan-without-collectors.php')
            ->create()
            ->getByType(AnalyzeCommand::class);

        $result = $command->analyze(['tests/EndToEnd/OnlyWithContext/rendered.twig'], new BufferedOutput(), new BufferedOutput(), false, false, null);

        $renderPoints = $result->context->getByTemplate(__DIR__ . '/rendered.twig');
        self::assertCount(1, $renderPoints);
        self::assertSame(__DIR__ . '/RenderPage.php', array_values($renderPoints)[0][0]->fileName);
    }
}

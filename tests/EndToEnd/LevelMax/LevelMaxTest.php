<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\LevelMax;

use Symfony\Component\Console\Tester\CommandTester;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;
use TwigStan\EndToEnd\AbstractEndToEndTestCase;

/**
 * Analyzes the templates with PHPStan at level max, which reports mixed types.
 */
final class LevelMaxTest extends AbstractEndToEndTestCase
{
    protected function getConfigurationFile(): string
    {
        return __DIR__ . '/../../twigstan-max.php';
    }

    public function test(): void
    {
        parent::runAnalysis(__DIR__);
    }

    /**
     * PHPStan prints instructions for AI agents after each run with errors. TwigStan prints them once, after its errors.
     */
    public function testAgentInstructions(): void
    {
        $command = ContainerFactory::fromFile(dirname(__DIR__, 3), $this->getConfigurationFile())
            ->create()
            ->getByType(AnalyzeCommand::class);

        $agent = getenv('AI_AGENT');
        putenv('AI_AGENT=1');

        try {
            $tester = new CommandTester($command);
            $tester->execute(['paths' => ['tests/EndToEnd/LevelMax']], ['capture_stderr_separately' => true]);
        } finally {
            putenv($agent === false ? 'AI_AGENT' : 'AI_AGENT=' . $agent);
        }

        $errorOutput = $tester->getErrorOutput();

        self::assertSame(1, substr_count($errorOutput, 'Instructions for interpreting errors'));
        self::assertGreaterThan(
            strrpos($errorOutput, '🔖'),
            strpos($errorOutput, 'Instructions for interpreting errors'),
        );
    }
}

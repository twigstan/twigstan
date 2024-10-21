<?php

declare(strict_types=1);

namespace TwigStan\Baseline;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;
use TwigStan\ErrorHelper;

final class BaselineTest extends TestCase
{
    private BufferedOutput $output;
    private BufferedOutput $errorOutput;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();
    }

    protected function onNotSuccessfulTest(Throwable $t): never
    {
        echo $this->output->fetch();
        echo $this->errorOutput->fetch();

        throw $t;
    }

    public function testBaseline(): void
    {
        $baseline = __DIR__ . '/baseline.php';

        $result = $this->getAnalyzeCommand(__DIR__ . '/twigstan.php')->analyze(
            ['tests/Baseline'],
            $this->output,
            $this->errorOutput,
            true,
            extension_loaded('xdebug') ? true : false,
            $baseline,
        );

        self::assertSame([], $result->errors);
        self::assertSame([], $result->fileSpecificErrors);

        self::assertFileEquals(__DIR__ . '/expected.php', $baseline);
    }

    public function testWithoutBaseline(): void
    {
        $result = $this->getAnalyzeCommand(__DIR__ . '/../twigstan.php')->analyze(
            ['tests/Baseline'],
            $this->output,
            $this->errorOutput,
            true,
            extension_loaded('xdebug') ? true : false,
            null,
        );

        ErrorHelper::assertAnalysisResultMatchesJsonFile($result, __DIR__);
    }

    private function getAnalyzeCommand(string $configurationFile): AnalyzeCommand
    {
        $container = ContainerFactory::fromFile(
            dirname(__DIR__, 2),
            $configurationFile,
        )->create();

        return $container->getByType(AnalyzeCommand::class);
    }
}

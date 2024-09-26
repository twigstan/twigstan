<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Path;
use Throwable;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;
use TwigStan\ErrorHelper;

abstract class AbstractEndToEndTestCase extends TestCase
{
    private AnalyzeCommand $command;
    private BufferedOutput $output;
    private BufferedOutput $errorOutput;

    protected function setUp(): void
    {
        $container = ContainerFactory::fromFile(
            dirname(__DIR__, 2),
            __DIR__ . '/../twigstan.php',
        )->create();
        $this->command = $container->getByType(AnalyzeCommand::class);

        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();
    }

    protected function onNotSuccessfulTest(Throwable $t): never
    {
        echo $this->output->fetch();
        echo $this->errorOutput->fetch();

        throw $t;
    }

    protected function runTests(string $directory): void
    {
        $result = $this->command->analyze(
            [Path::makeRelative($directory, dirname(__DIR__, 2))],
            $this->output,
            $this->errorOutput,
            extension_loaded('xdebug') ? true : false,
            extension_loaded('xdebug') ? true : false,
            null,
        );

        ErrorHelper::assertAnalysisResultMatchesJsonFile($result, $directory);
    }
}

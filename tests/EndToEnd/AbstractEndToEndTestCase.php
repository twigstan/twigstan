<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd;

use JsonException;
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

    /**
     * @param list<string> $files
     *
     * @throws Throwable
     * @throws JsonException
     */
    protected function runTests(string $directory, array $files = []): void
    {
        $relativeDirectory = Path::makeRelative($directory, dirname(__DIR__, 2));
        $result = $this->command->analyze(
            $files !== [] ? array_map(
                fn(string $file) => Path::join($relativeDirectory, $file),
                $files,
            ) : [$relativeDirectory],
            $this->output,
            $this->errorOutput,
            true,
            extension_loaded('xdebug') ? true : false,
            null,
        );

        ErrorHelper::assertAnalysisResultMatchesJsonFile($result, $directory, $files);

        if ($files !== []) {
            self::markTestIncomplete('This test was limited to selected files, therefore the test is not complete.');
        }
    }
}

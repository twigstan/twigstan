<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd;

use JsonException;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Throwable;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\Application\TwigStanAnalysisResult;
use TwigStan\DependencyInjection\ContainerFactory;
use TwigStan\ErrorHelper;

abstract class AbstractEndToEndTestCase extends TestCase
{
    private AnalyzeCommand $command;
    private BufferedOutput $output;
    private BufferedOutput $errorOutput;
    private TwigStanAnalysisResult $result;
    private string $directory;

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

        if (isset($this->result)) {
            $actualContext = ErrorHelper::contextToArray($this->result->context, $this->directory);
            echo "\nCollected template context:\n";
            echo json_encode($actualContext, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
        }

        throw $t;
    }

    /**
     * @param list<string> $files
     *
     * @throws Throwable
     * @throws JsonException
     */
    protected function runAnalysis(string $directory, array $files = []): void
    {
        $this->directory = $directory;
        $this->output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $relativeDirectory = Path::makeRelative($directory, dirname(__DIR__, 2));

        $paths = [$relativeDirectory];

        // When files are specified, we need to make sure that `*Test.php` files are also analyzed, because they could
        // be used to read context.
        if ($files !== []) {
            $paths = [
                ...array_map(
                    fn(string $file) => Path::join($relativeDirectory, $file),
                    $files,
                ),
                ...array_values(array_map(
                    fn(SplFileInfo $file) => $file->getPathname(),
                    iterator_to_array(Finder::create()->files()->name('*Test.php')->in($directory)->depth(0)),
                )),
            ];
        }

        $this->result = $this->command->analyze(
            $paths,
            $this->output,
            $this->errorOutput,
            true,
            extension_loaded('xdebug') ? true : false,
            null,
        );

        ErrorHelper::assertAnalysisResultMatchesJsonFile($this->result, $directory, $files);

        if ($files !== []) {
            self::markTestIncomplete('This test was limited to selected files, therefore the test is not complete.');
        }
    }
}

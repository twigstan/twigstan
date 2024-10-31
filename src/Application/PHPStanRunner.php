<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Neon\Neon;
use PhpParser\Node;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use TwigStan\PHPStan\Analysis\AnalysisResultFromJsonReader;
use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;
use TwigStan\PHPStan\Collector\TemplateContextCollector;

final readonly class PHPStanRunner
{
    /**
     * @param list<class-string<TemplateContextCollector<Node>>> $twigContextCollectors
     */
    public function __construct(
        private Filesystem $filesystem,
        private AnalysisResultFromJsonReader $analysisResultFromJsonReader,
        private string $phpstanBinPath,
        private string $phpstanConfigurationFile,
        private null | false | string $phpstanMemoryLimit,
        private string $currentWorkingDirectory,
        private string $tempDirectory,
        private array $twigContextCollectors,
    ) {}

    /**
     * @param list<string> $filesToAnalyze
     * @param list<string> $directoriesToAnalyze
     */
    public function run(
        OutputInterface $output,
        OutputInterface $errorOutput,
        string $environmentLoader,
        array $filesToAnalyze,
        array $directoriesToAnalyze,
        bool $debugMode,
        bool $xdebugMode,
        PHPStanRunMode $mode,
    ): PHPStanAnalysisResult {
        $configFile = Path::join($this->tempDirectory, sprintf('phpstan.%s.neon', $mode->value));
        $analysisResultJsonFile = Path::join($this->tempDirectory, 'phpstan', sprintf('%s.result.json', $mode->value));

        $services = [];

        if ($mode === PHPStanRunMode::CollectPhpRenderPoints) {
            foreach ($this->twigContextCollectors as $className) {
                $reflection = new ReflectionClass($className);

                $services[$reflection->getFileName()] = [
                    'class' => $className,
                    'tags' => ['phpstan.collector'],
                ];
            }
        }

        $parameters = [
            'tmpDir' => Path::join($this->tempDirectory, 'phpstan'),
            'resultCachePath' => Path::join($this->tempDirectory, 'phpstan', sprintf('%s.resultCache.php', $mode->value)),
            'paths!' => [
                ...$filesToAnalyze,
                ...$directoriesToAnalyze,
                ...array_keys($services),
            ],
            'scanDirectories' => $directoriesToAnalyze,
            'twigstan' => [
                'twigEnvironmentLoader' => $environmentLoader,
                'analysisResultJsonFile' => $analysisResultJsonFile,
                'mode' => $mode->value,
                'debugMode' => $debugMode,
            ],
        ];

        if ($mode === PHPStanRunMode::CollectPhpRenderPoints || $mode === PHPStanRunMode::CollectTwigRenderPoints) {
            $parameters['level'] = null;
            $parameters['customRulesetUsed'] = true;
        }

        $config = [
            'includes' => [
                $this->phpstanConfigurationFile,
                Path::join(dirname(__DIR__, 2), 'config', sprintf('phpstan.%s.neon', $mode->value)),
            ],
            'parameters' => $parameters,
            'services' => array_values($services),
        ];

        $this->filesystem->dumpFile(
            $configFile,
            Neon::encode($config, true),
        );

        $process = new Process(
            array_filter([
                PHP_BINARY,
                $xdebugMode ? '-d zend_extension=xdebug.so' : null,
                $this->phpstanMemoryLimit !== null ? sprintf('-d memory_limit=%s', $this->phpstanMemoryLimit !== false ? $this->phpstanMemoryLimit : '-1') : null,
                $this->phpstanBinPath,
                'analyse',
                sprintf('--configuration=%s', $configFile),
                sprintf(
                    '--error-format=%s',
                    'analysisResultToJson',
                ),
                $debugMode ? '-v' : null,
                $debugMode ? '--debug' : null,
                $xdebugMode ? '--xdebug' : null,
                '--ansi',
            ], fn($value) => ! is_null($value)),
            $this->currentWorkingDirectory,
            array_filter([
                'XDEBUG_MODE' => $xdebugMode ? 'debug' : null,
                'XDEBUG_TRIGGER' => $xdebugMode ? '1' : null,
            ], fn($value) => ! is_null($value)),
            timeout: null,
        );

        $output->writeln($process->getCommandLine(), OutputInterface::VERBOSITY_VERBOSE);

        $exitCode = $process->run(function ($type, $buffer) use ($errorOutput, $output): void {
            if ($type === Process::ERR) {
                $errorOutput->write($buffer);

                return;
            }

            $output->write($buffer);
        });

        return $this->analysisResultFromJsonReader->read(
            $analysisResultJsonFile,
            $exitCode,
        );
    }
}

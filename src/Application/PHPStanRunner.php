<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Neon\Neon;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use TwigStan\PHPStan\Analysis\AnalysisResultFromJsonReader;
use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;

final readonly class PHPStanRunner
{
    public function __construct(
        private Filesystem $filesystem,
        private AnalysisResultFromJsonReader $analysisResultFromJsonReader,
        private string $phpstanBinPath,
        private string $phpstanConfigurationFile,
        private null | false | string $phpstanMemoryLimit,
        private string $currentWorkingDirectory,
    ) {}

    /**
     * @param list<string> $pathsToAnalyze
     */
    public function run(
        OutputInterface $output,
        OutputInterface $errorOutput,
        string $environmentLoader,
        array $pathsToAnalyze,
        bool $debugMode,
        bool $xdebugMode,
        bool $collectOnly = false,
    ): PHPStanAnalysisResult {
        $tempConfigFile = tempnam(sys_get_temp_dir(), 'twigstan-phpstan-');
        $this->filesystem->rename($tempConfigFile, $tempConfigFile . '.neon');
        $tempConfigFile = $tempConfigFile . '.neon';

        $analysisResultJsonFile = tempnam(sys_get_temp_dir(), 'twigstan-');
        $this->filesystem->remove($analysisResultJsonFile);

        $parameters = [
            'twigstan' => [
                'twigEnvironmentLoader' => $environmentLoader,
                'analysisResultJsonFile' => $analysisResultJsonFile,
                'collectOnly' => $collectOnly,
                'debugMode' => $debugMode,
            ],
        ];

        if ($collectOnly) {
            $parameters['level'] = null;
            $parameters['customRulesetUsed'] = true;
        }

        $this->filesystem->dumpFile(
            $tempConfigFile,
            Neon::encode([
                'includes' => [
                    $this->phpstanConfigurationFile,
                    __DIR__ . '/../../config/phpstan.neon',
                ],
                'parameters' => $parameters,
            ]),
        );

        $process = new Process(
            array_filter([
                PHP_BINARY,
                $xdebugMode ? '-d zend_extension=xdebug.so' : null,
                $this->phpstanMemoryLimit !== null ? sprintf('-d memory_limit=%s', $this->phpstanMemoryLimit !== false ? $this->phpstanMemoryLimit : '-1') : null,
                $this->phpstanBinPath,
                'analyse',
                sprintf('--configuration=%s', $tempConfigFile),
                sprintf(
                    '--error-format=%s',
                    'analysisResultToJson',
                ),
                $debugMode ? '-v' : null,
                $debugMode ? '--debug' : null,
                $xdebugMode ? '--xdebug' : null,
                '--ansi',
                ...$pathsToAnalyze,
            ], fn($value) => ! is_null($value)),
            $this->currentWorkingDirectory,
            array_filter([
                'XDEBUG_MODE' => $xdebugMode ? 'debug' : null,
                'XDEBUG_TRIGGER' => $xdebugMode ? '1' : null,
            ], fn($value) => ! is_null($value)),
            timeout: null,
        );

        $output->writeln($process->getCommandLine(), OutputInterface::VERBOSITY_VERBOSE);

        $process->run(function ($type, $buffer) use ($errorOutput, $output): void {
            if ($type === Process::ERR) {
                $errorOutput->write($buffer);

                return;
            }

            $output->write($buffer);
        });

        $analysisResult = $this->analysisResultFromJsonReader->read($analysisResultJsonFile);

        $this->filesystem->remove($tempConfigFile);
        $this->filesystem->remove($analysisResultJsonFile);

        return $analysisResult;
    }
}

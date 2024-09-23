<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Neon\Neon;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use TwigStan\PHPStan\Analysis\AnalysisResultFromJsonReader;
use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;
use TwigStan\Processing\Flattening\FlatteningResultCollection;
use TwigStan\Processing\ScopeInjection\ScopeInjectionResultCollection;

final readonly class PHPStanRunner
{
    public function __construct(
        private Filesystem $filesystem,
        private AnalysisResultFromJsonReader $analysisResultFromJsonReader,
        private string $currentWorkingDirectory,
    ) {}

    /**
     * @param list<string> $pathsToAnalyze
     */
    public function run(
        OutputInterface $output,
        OutputInterface $errorOutput,
        string $phpstanConfigurationFile,
        string $environmentLoader,
        array $pathsToAnalyze,
        bool $debugMode,
        bool $xdebugMode,
        FlatteningResultCollection | ScopeInjectionResultCollection $mapping,
        bool $collectOnly = false,
    ): PHPStanAnalysisResult {
        $tempConfigFile = tempnam(sys_get_temp_dir(), 'twigstan-phpstan-');
        $this->filesystem->rename($tempConfigFile, $tempConfigFile . '.neon');
        $tempConfigFile = $tempConfigFile . '.neon';

        $analysisResultJsonFile = tempnam(sys_get_temp_dir(), 'twigstan-');
        $this->filesystem->remove($analysisResultJsonFile);

        $this->filesystem->dumpFile(
            $tempConfigFile,
            Neon::encode([
                'includes' => [
                    $phpstanConfigurationFile,
                ],
                'parameters' => [
                    'level' => $collectOnly ? null : 8,
                    'customRulesetUsed' => $collectOnly,
                    'twigstan' => [
                        'environmentLoader' => $environmentLoader,
                        'analysisResultJsonFile' => $analysisResultJsonFile,
                    ],
                ],
            ]),
        );

        // @todo While developing TwigStan this is handy to have
        //$process = new Process([
        //    PHP_BINARY,
        //    'vendor/bin/phpstan',
        //    'clear-result-cache',
        //    sprintf('--configuration=%s', $tempConfigFile),
        //], $this->currentWorkingDirectory);
        //$process->run();

        $process = new Process(
            array_filter([
                PHP_BINARY,
                $xdebugMode ? '-dzend_extension=xdebug.so' : null,
                'vendor/bin/phpstan',
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
            ], fn($value) => !is_null($value)),
            $this->currentWorkingDirectory,
            array_filter([
                'XDEBUG_MODE' => $xdebugMode ? 'debug' : null,
                'XDEBUG_TRIGGER' => $xdebugMode ? '1' : null,
            ], fn($value) => !is_null($value)),
            timeout: null,
        );

        $output->writeln($process->getCommandLine(), OutputInterface::VERBOSITY_VERBOSE);

        $process->run(function ($type, $buffer) use ($errorOutput, $output): void {
            if (Process::ERR === $type) {
                $errorOutput->write($buffer);
                return;
            }

            $output->write($buffer);
        });

        $analysisResult = $this->analysisResultFromJsonReader->read($analysisResultJsonFile, $mapping);

        $this->filesystem->remove($tempConfigFile);
        $this->filesystem->remove($analysisResultJsonFile);

        return $analysisResult;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Neon\Neon;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final readonly class PHPStanRunner
{
    public function __construct(
        private Filesystem $filesystem,
        private string $currentWorkingDirectory,
    ) {}

    public function run(
        OutputInterface $output,
        OutputInterface $errorOutput,
        string $phpstanConfigurationFile,
        string $environmentLoader,
        string $directoryToAnalyse,
        string $analysisResultJsonFile,
        bool $debugMode,
        bool $xdebugMode,
    ): int {
        $tempConfigFile = tempnam(sys_get_temp_dir(), 'twigstan-phpstan-');
        $this->filesystem->rename($tempConfigFile, $tempConfigFile . '.neon');
        $tempConfigFile = $tempConfigFile . '.neon';

        $this->filesystem->dumpFile(
            $tempConfigFile,
            Neon::encode([
                'includes' => [
                    $phpstanConfigurationFile,
                ],
                'parameters' => [
                    'twigstan' => [
                        'environmentLoader' => $environmentLoader,
                        'analysisResultJsonFile' => $analysisResultJsonFile,
                    ],
                ],
            ]),
        );

        // @todo While developing TwigStan this is handy to have
        $process = new Process([
            PHP_BINARY,
            'vendor/bin/phpstan',
            'clear-result-cache',
            sprintf('--configuration=%s', $tempConfigFile),
        ], $this->currentWorkingDirectory);
        $process->run();

        $process = new Process(array_filter([
            PHP_BINARY,
            'vendor/bin/phpstan',
            'analyse',
            $directoryToAnalyse,
            sprintf('--configuration=%s', $tempConfigFile),
            '--error-format=analysisResultToJson',
            $debugMode ? '-v' : null,
            $debugMode ? '--debug' : null,
            $xdebugMode ? '--xdebug' : null,
            '--ansi',
        ]), $this->currentWorkingDirectory);

        $output->writeln($process->getCommandLine(), OutputInterface::VERBOSITY_VERBOSE);

        $exitCode = $process->run(function ($type, $buffer) use ($errorOutput, $output): void {
            if (Process::ERR === $type) {
                $errorOutput->write($buffer);
                return;
            }

            $output->write($buffer);
        });

        $this->filesystem->remove($tempConfigFile);

        return $exitCode;
    }
}

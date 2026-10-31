<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Neon\Neon;
use PhpParser\Node;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use TwigStan\PHPStan\Analysis\AnalysisResultFromJsonReader;
use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;
use TwigStan\PHPStan\Collector\TemplateContextCollector;
use TwigStan\Twig\EnvironmentFingerprint;

final readonly class PHPStanRunner
{
    private const string AGENT_INSTRUCTIONS_START = 'Instructions for interpreting errors';
    private const string AGENT_INSTRUCTIONS_END = "Do not widen parameter or return types just to make the error go away.\n";

    /**
     * @param list<class-string<TemplateContextCollector<Node>>> $twigContextCollectors
     * @param list<string> $phpExtensions
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
        private ?string $appUserClass,
        private array $phpExtensions,
        private EnvironmentFingerprint $environmentFingerprint,
    ) {}

    /**
     * @param list<string> $filesToAnalyze
     * @param list<string> $directoriesToAnalyze
     * @param list<string> $excludePaths fnmatch patterns
     * @param positive-int $run
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
        array $excludePaths = [],
        int $run = 1,
    ): PHPStanAnalysisResult {
        $configFile = Path::join($this->tempDirectory, sprintf('phpstan.%s.neon', $mode->value));
        $analysisResultJsonFile = Path::join($this->tempDirectory, 'phpstan', sprintf('%s.result.json', $mode->value));

        $services = [];

        if ($mode === PHPStanRunMode::CollectPhpRenderPoints) {
            foreach ($this->twigContextCollectors as $className) {
                $fileName = (new ReflectionClass($className))->getFileName();

                if ($fileName === false) {
                    throw new RuntimeException(sprintf('Twig context collector %s must be a user-defined class.', $className));
                }

                $services[$fileName] = [
                    'class' => $className,
                    'tags' => ['phpstan.collector'],
                ];
            }
        }

        $parameters = [
            'tmpDir' => Path::join($this->tempDirectory, 'phpstan'),
            // Each run analyses other templates, so each has its own result cache.
            'resultCachePath' => Path::join($this->tempDirectory, 'phpstan', $mode === PHPStanRunMode::CollectPhpRenderPoints ? sprintf('%s.resultCache.php', $mode->value) : sprintf('%s.%d.resultCache.php', $mode->value, $run)),
            'paths!' => [
                ...$filesToAnalyze,
                ...$directoriesToAnalyze,
                ...array_keys($services),
            ],
            // The PHP paths are analysed, the directories of compiled templates are also scanned for the classes of other templates.
            'scanDirectories' => $mode === PHPStanRunMode::CollectPhpRenderPoints ? [] : $directoriesToAnalyze,
            'twigstan' => [
                'twigEnvironmentLoader' => $environmentLoader,
                'analysisResultJsonFile' => $analysisResultJsonFile,
                'mode' => $mode->value,
                'debugMode' => $debugMode,
                'appUserClass' => $this->appUserClass,
                // The render points do not depend on the Twig environment, so their result cache is kept when it changes.
                'environmentFingerprint' => $mode === PHPStanRunMode::CollectPhpRenderPoints ? null : $this->environmentFingerprint->get(),
            ],
        ];

        if ($mode === PHPStanRunMode::CollectPhpRenderPoints) {
            $parameters['fileExtensions'] = $this->phpExtensions;
        }

        if ($excludePaths !== []) {
            // (?) makes paths without wildcards optional, as in TwigStan.
            $parameters['excludePaths'] = ['analyse' => array_map(fn(string $path) => sprintf('%s (?)', $path), $excludePaths)];
        }

        if ($this->appUserClass !== null) {
            $parameters['stubFiles'] = [Path::join(dirname(__DIR__, 2), 'stubs', 'Symfony', 'AppVariable.stub')];
        }

        if ($mode === PHPStanRunMode::CollectPhpRenderPoints || $mode === PHPStanRunMode::CollectTwigBlockContexts) {
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

        // PHPStan prints instructions for AI agents at the end of each run with errors. Those errors are
        // in the compiled templates, so the instructions are kept for TwigStan to print once after its errors.
        $agentInstructions = null;
        $capturing = false;
        $exitCode = $process->run(function ($type, $buffer) use ($errorOutput, $output, &$agentInstructions, &$capturing): void {
            if ($type !== Process::ERR) {
                $output->write($buffer);

                return;
            }

            if ( ! $capturing) {
                $start = $agentInstructions === null ? strpos($buffer, self::AGENT_INSTRUCTIONS_START) : false;

                if ($start === false) {
                    $errorOutput->write($buffer);

                    return;
                }

                $errorOutput->write(substr($buffer, 0, $start));
                $buffer = substr($buffer, $start);
                $agentInstructions = '';
                $capturing = true;
            }

            $agentInstructions .= $buffer;
            $end = strpos($agentInstructions, self::AGENT_INSTRUCTIONS_END);

            if ($end === false) {
                return;
            }

            $end += strlen(self::AGENT_INSTRUCTIONS_END);
            $errorOutput->write(substr($agentInstructions, $end));
            $agentInstructions = substr($agentInstructions, 0, $end);
            $capturing = false;
        });

        return $this->analysisResultFromJsonReader->read(
            $analysisResultJsonFile,
            $exitCode,
        )->withAgentInstructions($agentInstructions);
    }
}

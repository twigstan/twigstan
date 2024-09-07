<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;
use TwigStan\PHPStan\Collector\TemplateRenderContextCollector;
use TwigStan\Processing\Compilation\CompilationResultCollection;
use TwigStan\Processing\Compilation\TwigCompiler;
use TwigStan\Processing\Flattening\TwigFlattener;
use TwigStan\Processing\ScopeInjection\TwigScopeInjector;
use TwigStan\Twig\DependencyFinder;
use TwigStan\Twig\DependencySorter;
use TwigStan\Twig\TwigFileNormalizer;

#[AsCommand(name: 'analyze', aliases: ['analyse'])]
final class AnalyzeCommand extends Command
{
    /**
     * @param list<string> $directories
     * @param list<string> $excludes
     */
    public function __construct(
        private TwigCompiler $twigCompiler,
        private TwigFlattener $twigFlattener,
        private TwigScopeInjector $twigScopeInjector,
        private DependencyFinder $dependencyFinder,
        private DependencySorter $dependencySorter,
        private TwigFileNormalizer $twigFileNormalizer,
        private PHPStanRunner $phpStanRunner,
        private string $environmentLoader,
        private array $directories = [],
        private array $excludes = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Directories or files to analyze');
        $this->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug mode');
        $this->addOption('xdebug', null, InputOption::VALUE_NONE, 'Enable xdebug mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();

        $debugMode = $input->getOption('debug') === true;
        $xdebugMode = $input->getOption('xdebug') === true;
        $errorOutput = $output->getErrorOutput();

        $compilationDirectory = Path::normalize(sys_get_temp_dir() . '/twigstan/compilation');
        $filesystem->remove($compilationDirectory);
        $filesystem->mkdir($compilationDirectory);

        $flatteningDirectory = Path::normalize(sys_get_temp_dir() . '/twigstan/flattening');
        $filesystem->remove($flatteningDirectory);
        $filesystem->mkdir($flatteningDirectory);

        $scopeInjectionDirectory = Path::normalize(sys_get_temp_dir() . '/twigstan/scope-injection');
        $filesystem->remove($scopeInjectionDirectory);
        $filesystem->mkdir($scopeInjectionDirectory);

        $workingDirectory = getcwd();
        $finder = $this->getFinder(
            $workingDirectory,
            $input->getArgument('paths'),
        );

        $twigFileNames = [];
        $phpFileNames = [];
        foreach ($finder as $file) {
            if ($file->getExtension() === 'php') {
                $phpFileNames[] = $file->getRealPath();
                continue;
            }

            if ($file->getExtension() == 'twig') {
                $twigFileNames[] = $this->twigFileNormalizer->normalize($file->getRealPath());
                continue;
            }

            $errorOutput->writeln(sprintf('<error>Unsupported file type: %s</error>', $file->getRealPath()));
        }

        if ($twigFileNames === []) {
            $output->writeln('<error>No templates found</error>');

            return Command::FAILURE;
        }

        $twigFileNamesToAnalyze = $twigFileNames;

        $output->writeln(sprintf('<info>Finding dependencies for %d templates...</info>', count($twigFileNamesToAnalyze)));

        // Maybe this should be done using a graph later.
        $dependencies = $this->dependencyFinder->getDependencies($twigFileNames);
        $twigFileNames = array_values(array_unique([...$dependencies, ...$twigFileNames]));
        $twigFileNames = $this->dependencySorter->sortByDependencies($twigFileNames);

        $count = count($twigFileNames);

        $output->writeln(sprintf('<info>Found %d dependencies...</info>', $count - count($twigFileNamesToAnalyze)));

        $output->writeln(sprintf('<info>Compiling %d templates...</info>', $count));

        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();

        $compilationResults = new CompilationResultCollection();
        foreach ($twigFileNames as $twigFile) {
            try {
                $compilationResults = $compilationResults->with(
                    $this->twigCompiler->compile(
                        $twigFile,
                        $compilationDirectory,
                    ),
                );
            } catch (Throwable $error) {
                $progressBar->clear();
                $errorOutput->writeln(sprintf(
                    'Error compiling %s: %s',
                    Path::makeRelative($twigFile, $workingDirectory),
                    $error->getMessage(),
                ));

                if ($debugMode) {
                    throw $error;
                }
            } finally {
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $progressBar->clear();

        $output->writeln(sprintf('<info>Flattening %d templates...</info>', $count));

        $flatteningResults = $this->twigFlattener->flatten(
            $compilationResults,
            $flatteningDirectory,
        );

        $output->writeln('<info>Collecting scopes...</info>');

        $analysisResult = $this->phpStanRunner->run(
            $output,
            $errorOutput,
            __DIR__ . '/../../config/phpstan.neon',
            $this->environmentLoader,
            [
                ...$phpFileNames,
                $flatteningDirectory,
            ],
            $debugMode,
            $xdebugMode,
            $flatteningResults,
            collectOnly: true,
        );

        foreach ($analysisResult->notFileSpecificErrors as $fileSpecificError) {
            $errorOutput->writeln(sprintf('<error>Error</error> %s', $fileSpecificError));
        }

        if ($analysisResult->notFileSpecificErrors !== []) {
            return self::FAILURE;
        }

        /**
         * @var array<string, array<string, list<int>>> $templateToRenderPoint
         */
        $templateToRenderPoint = [];
        foreach ($analysisResult->collectedData as $data) {
            if ($data->collecterType !== TemplateRenderContextCollector::class) {
                continue;
            }

            foreach ($data->data as $renderData) {
                $templateToRenderPoint[$renderData['template']][$data->filePath][] = $renderData['startLine'];
            }
        }

        $output->writeln('<info>Injecting scope into templates...</info>');

        $scopeInjectionResults = $this->twigScopeInjector->inject($analysisResult->collectedData, $flatteningResults, $scopeInjectionDirectory);

        // Disable the extension installer for now
        // This disables the noise from `phpstan/phpstan-strict-rules`
        if (file_exists('vendor/phpstan/extension-installer/src/GeneratedConfig.php')) {
            rename('vendor/phpstan/extension-installer/src/GeneratedConfig.php', 'vendor/phpstan/extension-installer/src/GeneratedConfig.php.bak');
        }

        $phpFileNamesToAnalyze = array_map(
            fn($twigFileName) => $scopeInjectionResults->getByTwigFileName($twigFileName)->phpFile,
            $twigFileNamesToAnalyze,
        );

        try {
            $output->writeln('<info>Analyzing templates</info>');

            $analysisResult = $this->phpStanRunner->run(
                $output,
                $errorOutput,
                __DIR__ . '/../../config/phpstan.neon',
                $this->environmentLoader,
                $phpFileNamesToAnalyze,
                $debugMode,
                $xdebugMode,
                $scopeInjectionResults,
            );

            foreach ($analysisResult->notFileSpecificErrors as $fileSpecificError) {
                $errorOutput->writeln(sprintf('<error>Error</error> %s', $fileSpecificError));
            }

            foreach ($analysisResult->errors as $error) {
                $errorOutput->writeln($error->message);

                if ($error->tip !== null) {
                    foreach (explode("\n", $error->tip) as $line) {
                        $errorOutput->writeLn(sprintf("💡 <fg=blue>%s</>", ltrim($line, ' •')));
                    }
                }

                if ($error->identifier !== null) {
                    $errorOutput->writeLn(sprintf("🔖 <fg=blue>%s</>", $error->identifier));
                }

                $line = $error->phpLine ?? 0;

                $errorOutput->writeln(
                    sprintf(
                        '🐘 <href=%s>%s:%d</>',
                        str_replace(
                            ['%file%', '%line%'],
                            [$error->phpFile, $line],
                            "phpstorm://open?file=%file%&line=%line%",
                        ),
                        sprintf(
                            'compiled_%s.php',
                            preg_replace(
                                '/(\.html)?\.twig\.\w+\.php$/',
                                '',
                                basename($error->phpFile),
                            ),
                        ),
                        $line,
                    ),
                );

                $lastTwigFileName = null;
                foreach ($error->sourceLocation as $sourceLocation) {
                    $twigFileName = $compilationResults
                        ->getByTwigFileName($sourceLocation->fileName)
                        ->twigFilePath;

                    $lastTwigFileName = $sourceLocation->fileName;

                    $errorOutput->writeln(
                        sprintf(
                            '🌱 <href=%s>%s:%d</>',
                            str_replace(
                                ['%file%', '%line%'],
                                [$twigFileName, $sourceLocation->lineNumber],
                                "phpstorm://open?file=%file%&line=%line%",
                            ),
                            Path::makeRelative($twigFileName, $workingDirectory),
                            $sourceLocation->lineNumber,
                        ),
                    );
                }

                if ($lastTwigFileName !== null) {
                    foreach ($templateToRenderPoint[$lastTwigFileName] ?? [] as $renderPointFileName => $lineNumbers) {
                        foreach ($lineNumbers as $lineNumber) {
                            $errorOutput->writeln(
                                sprintf(
                                    '🕹️ <href=%s>%s:%d</>',
                                    str_replace(
                                        ['%file%', '%line%'],
                                        [$renderPointFileName, $lineNumber],
                                        "phpstorm://open?file=%file%&line=%line%",
                                    ),
                                    Path::makeRelative($renderPointFileName, $workingDirectory),
                                    $lineNumber,
                                ),
                            );
                        }
                    }
                }

                $errorOutput->writeln('');
            }

            if (count($analysisResult->errors) > 0) {
                $output->writeln(sprintf('<error>Found %d errors</error>', count($analysisResult->errors)));

                return self::FAILURE;
            }
            $output->writeln('<info>No errors found</info>');

            return self::SUCCESS;
        } finally {
            if (file_exists('vendor/phpstan/extension-installer/src/GeneratedConfig.php.bak')) {
                rename('vendor/phpstan/extension-installer/src/GeneratedConfig.php.bak', 'vendor/phpstan/extension-installer/src/GeneratedConfig.php');
            }
        }
    }

    /**
     * @param list<string> $paths
     *
     * @return array<SplFileInfo>
     */
    private function getFinder(string $currentWorkingDirectory, array $paths): array
    {
        if ($paths === []) {
            $paths = $this->directories;
        }

        $paths = array_unique($paths);

        $directories = [];
        $files = [];
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $directories[] = $path;
                continue;
            }

            if (is_file($path)) {
                $files[] = new SplFileInfo(
                    $currentWorkingDirectory . DIRECTORY_SEPARATOR . $path,
                    dirname($path),
                    $path,
                );
            }
        }

        if ($files === [] && $directories === []) {
            return [];
        }

        $found = Finder::create()
            ->files()
            ->name(['*.twig', '*.php'])
            ->notName('*.untrack.php') // @todo remove later
            ->in($directories)
            ->append($files)
            ->filter(function (SplFileInfo $file) {
                foreach ($this->excludes as $exclude) {
                    if (fnmatch($exclude, $file->getRealPath(), FNM_NOESCAPE)) {
                        return false;
                    }
                }

                return true;
            });


        return iterator_to_array($found);
    }
}

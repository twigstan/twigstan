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
use TwigStan\PHPStan\Analysis\AnalysisResultFromJsonReader;
use TwigStan\Twig\Transforming\TransformResult;
use TwigStan\Twig\Transforming\TwigTransformer;

#[AsCommand(name: 'analyze', aliases: ['analyse'])]
final class AnalyzeCommand extends Command
{
    public function __construct(
        private TwigTransformer $twigTransformer,
        private AnalysisResultFromJsonReader $analysisResultFromJsonReader,
        private PHPStanRunner $phpStanRunner,
        private string $environmentLoader,
        private array $directories = [],
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

        $tempDir = Path::normalize(sys_get_temp_dir() . '/twigstan');

        $analysisResultJsonFile = tempnam(sys_get_temp_dir(), 'twigstan-');

        $filesystem->remove($tempDir);
        $filesystem->remove($analysisResultJsonFile);
        $filesystem->mkdir($tempDir);

        $workingDirectory = getcwd();
        $finder = $this->getFinder($workingDirectory, $input->getArgument('paths'));
        $count = count($finder);

        if ($count === 0) {
            $output->writeln('<error>No templates found</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Compiling %d templates...</info>', $count));

        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();

        /**
         * @var array<string, TransformResult> $mapping
         */
        $mapping = [];
        foreach ($finder as $twigFile) {
            try {
                $transformResult = $this->twigTransformer->transform($twigFile->getRealPath(), $tempDir);

                $mapping[$transformResult->phpFile] = $transformResult;
            } catch (Throwable $error) {
                $progressBar->clear();
                $errorOutput->writeln(sprintf(
                    'Error compiling %s: %s',
                    $twigFile->getRelativePathname(),
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

        // Disable the extension installer for now
        // This disables the noise from `phpstan/phpstan-strict-rules`
        if (file_exists('vendor/phpstan/extension-installer/src/GeneratedConfig.php')) {
            rename('vendor/phpstan/extension-installer/src/GeneratedConfig.php', 'vendor/phpstan/extension-installer/src/GeneratedConfig.php.bak');
        }

        try {
            $output->writeln('<info>Analyzing templates</info>');

            $exitCode = $this->phpStanRunner->run(
                $output,
                $errorOutput,
                __DIR__ . '/../../config/phpstan.neon',
                $this->environmentLoader,
                $tempDir,
                $analysisResultJsonFile,
                $debugMode,
                $xdebugMode,
            );

            if (file_exists($analysisResultJsonFile)) {
                $analysisResult = $this->analysisResultFromJsonReader->read($analysisResultJsonFile, $mapping);

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
                            '↳ <href=%s>%s:%d</>',
                            str_replace(
                                ['%file%', '%line%'],
                                [$error->phpFile, $line],
                                "phpstorm://open?file=%file%&line=%line%",
                            ),
                            $this->getRelativePath($workingDirectory, $error->phpFile),
                            $line,
                        ),
                    );

                    if ($error->twigFile === null) {
                        continue;
                    }

                    $errorOutput->writeln(
                        sprintf(
                            '↳ <href=%s>%s:%d</>',
                            str_replace(
                                ['%file%', '%line%'],
                                [$error->twigFile, $error->twigLine],
                                "phpstorm://open?file=%file%&line=%line%",
                            ),
                            $this->getRelativePath($workingDirectory, $error->twigFile),
                            $error->twigLine,
                        ),
                    );
                    $errorOutput->writeln('');
                }

                if (count($analysisResult->errors) > 0) {
                    $output->writeln(sprintf('<error>Found %d errors</error>', count($analysisResult->errors)));
                } else {
                    $output->writeln('<info>No errors found</info>');
                }
            }

            return $exitCode;
        } finally {
            if (file_exists('vendor/phpstan/extension-installer/src/GeneratedConfig.php.bak')) {
                rename('vendor/phpstan/extension-installer/src/GeneratedConfig.php.bak', 'vendor/phpstan/extension-installer/src/GeneratedConfig.php');
            }

            $filesystem->remove($analysisResultJsonFile);
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

        return iterator_to_array(Finder::create()
            ->files()
            ->name('*.twig')
            ->in($directories)
            ->append($files));
    }

    private function getRelativePath(string $workingDirectory, string $file): string
    {
        return str_replace($workingDirectory . '/', '', $file);
    }
}

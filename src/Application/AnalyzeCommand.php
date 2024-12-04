<?php

declare(strict_types=1);

namespace TwigStan\Application;

use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Throwable;
use TwigStan\Error\Baseline\PhpBaselineDumper;
use TwigStan\Error\BaselineError;
use TwigStan\Error\BaselineErrorFilter;
use TwigStan\Error\ErrorCollapser;
use TwigStan\Error\ErrorFilter;
use TwigStan\Error\ErrorToSourceFileMapper;
use TwigStan\Error\ErrorTransformer;
use TwigStan\Error\IgnoreByIdentifierWhenSourceLocationHasPrevious;
use TwigStan\Error\IgnoreError;
use TwigStan\Finder\FilesFinder;
use TwigStan\Finder\GivenFilesFinder;
use TwigStan\PHPStan\Analysis\Error;
use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;
use TwigStan\Processing\Compilation\CompilationResultCollection;
use TwigStan\Processing\Compilation\TwigCompiler;
use TwigStan\Processing\Flattening\TwigFlattener;
use TwigStan\Processing\ScopeInjection\TwigScopeInjector;
use TwigStan\Processing\TemplateContext;
use TwigStan\Processing\TemplateContextFactory;
use TwigStan\Twig\DependencyFinder;
use TwigStan\Twig\DependencySorter;
use TwigStan\Twig\Metadata\MetadataRegistry;
use TwigStan\Twig\SourceLocation;

final class AnalyzeCommand extends Command
{
    /**
     * @param list<string> $phpExtensions
     * @param list<string> $twigExtensions
     */
    public function __construct(
        private TwigCompiler $twigCompiler,
        private TwigFlattener $twigFlattener,
        private TwigScopeInjector $twigScopeInjector,
        private DependencyFinder $dependencyFinder,
        private DependencySorter $dependencySorter,
        private PHPStanRunner $phpStanRunner,
        private Filesystem $filesystem,
        private FilesFinder $phpFilesFinder,
        private FilesFinder $twigFilesFinder,
        private GivenFilesFinder $givenFilesFinder,
        private ErrorFilter $errorFilter,
        private BaselineErrorFilter $baselineErrorFilter,
        private ErrorCollapser $errorCollapser,
        private ErrorTransformer $errorTransformer,
        private ErrorToSourceFileMapper $errorToSourceFileMapper,
        private TemplateContextFactory $templateContextFactory,
        private MetadataRegistry $metadataRegistry,
        private string $environmentLoader,
        private string $tempDirectory,
        private string $currentWorkingDirectory,
        private string $configurationFile,
        private ?string $baselineFile,
        private bool $onlyAnalyzeTemplatesWithContext,
        private array $phpExtensions,
        private array $twigExtensions,
        private ?string $editorUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Directories or files to analyze');
        $this->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug mode');
        $this->addOption('xdebug', null, InputOption::VALUE_NONE, 'Enable xdebug mode');
        $this->addOption('generate-baseline', 'b', InputOption::VALUE_OPTIONAL, 'Path to a file where the baseline should be saved', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $generateBaselineFile = $input->getOption('generate-baseline');

        if ($generateBaselineFile === null) {
            $generateBaselineFile = $this->baselineFile ?? Path::join($this->currentWorkingDirectory, 'twigstan-baseline.php');
        } elseif ($generateBaselineFile !== false) {
            if (Path::getExtension($input->getOption('generate-baseline')) !== 'php') {
                $errorOutput->writeln('<error>Baseline file must have .php extension</error>');

                return self::FAILURE;
            }

            $generateBaselineFile = Path::makeAbsolute($generateBaselineFile, $this->currentWorkingDirectory);
        } else {
            $generateBaselineFile = null;
        }

        $result = $this->analyze(
            $input->getArgument('paths'),
            $output,
            $errorOutput,
            $input->getOption('debug') === true,
            $input->getOption('xdebug') === true,
            $generateBaselineFile,
        );

        if ($generateBaselineFile !== null) {
            return self::SUCCESS;
        }

        foreach ($result->errors as $error) {
            $errorOutput->writeln($error->message);

            if ($error->tip !== null) {
                foreach (explode("\n", $error->tip) as $line) {
                    $errorOutput->writeln(sprintf('💡 <fg=blue>%s</>', ltrim($line, ' •')));
                }
            }

            if ($error->identifier !== null) {
                $errorOutput->writeln(sprintf('🔖 <fg=blue>%s</>', $error->identifier));
            }

            if ($error->phpFile !== null && $error->phpLine !== null) {
                $errorOutput->write('🐘 ');
                $errorOutput->writeln($this->linkify(
                    $error->phpFile,
                    sprintf(
                        'compiled_%s.php',
                        preg_replace(
                            '/(\.html)?\.twig\.\w+\.php$/',
                            '',
                            basename($error->phpFile),
                        ),
                    ),
                    $error->phpLine,
                ));
            }

            if ($error->twigSourceLocation !== null) {
                foreach ($error->twigSourceLocation as $sourceLocation) {
                    $errorOutput->write('🌱 ');
                    $errorOutput->writeln($this->linkify(
                        $sourceLocation->fileName,
                        Path::makeRelative($sourceLocation->fileName, $this->currentWorkingDirectory),
                        $sourceLocation->lineNumber,
                    ));
                }
            }

            foreach ($error->renderPoints as $renderPoint) {
                $errorOutput->write('🎯 ');
                $errorOutput->writeln($this->linkify(
                    $renderPoint->sourceLocation->fileName,
                    Path::makeRelative($renderPoint->sourceLocation->fileName, $this->currentWorkingDirectory),
                    $renderPoint->sourceLocation->lineNumber,
                ));

                if ($output->isVeryVerbose()) {
                    $errorOutput->write('📝 ');
                    $errorOutput->writeln($renderPoint->context);
                }
            }

            $errorOutput->writeln('');
        }

        if (count($result->errors) > 0) {
            $output->writeln(sprintf('<error>Found %d %s</error>', count($result->errors), count($result->errors) === 1 ? 'error' : 'errors'));

            return self::FAILURE;
        }

        $output->writeln('No errors found');

        return self::SUCCESS;
    }

    /**
     * @param list<string> $paths
     *
     * @throws Throwable
     */
    public function analyze(
        array $paths,
        OutputInterface $output,
        OutputInterface $errorOutput,
        bool $debugMode,
        bool $xdebugMode,
        ?string $generateBaselineFile,
    ): TwigStanAnalysisResult {
        $output->writeln('TwigStan by Ruud Kamphuis and contributors.');

        $compilationDirectory = Path::normalize($this->tempDirectory . '/compilation');
        $this->filesystem->remove($compilationDirectory);
        $this->filesystem->mkdir($compilationDirectory);

        $flatteningDirectory = Path::normalize($this->tempDirectory . '/flattening');
        $this->filesystem->remove($flatteningDirectory);
        $this->filesystem->mkdir($flatteningDirectory);

        $scopeInjectionDirectory = Path::normalize($this->tempDirectory . '/scope-injection');
        $this->filesystem->remove($scopeInjectionDirectory);
        $this->filesystem->mkdir($scopeInjectionDirectory);

        if ($paths !== []) {
            $files = $this->givenFilesFinder->find($paths);
        } else {
            $files = [...$this->phpFilesFinder->find(), ...$this->twigFilesFinder->find()];
        }

        $twigFileNames = [];
        $phpFileNames = [];
        foreach ($files as $file) {
            if (in_array($file->getExtension(), $this->phpExtensions, true)) {
                $phpFileNames[] = $file->getRealPath();

                continue;
            }

            if (in_array($file->getExtension(), $this->twigExtensions, true)) {
                $twigFileNames[] = $file->getRealPath();

                continue;
            }

            $errorOutput->writeln(sprintf('<error>Unsupported file type: %s</error>', $file->getRealPath()));
        }

        if ($twigFileNames === []) {
            $output->writeln('<error>No templates found</error>');

            return new TwigStanAnalysisResult();
        }

        $twigFileNamesToAnalyze = $twigFileNames;

        $output->writeln('Collecting scopes from render points...');

        $analysisResult = $this->phpStanRunner->run(
            $output,
            $errorOutput,
            $this->environmentLoader,
            $phpFileNames,
            [],
            $debugMode,
            $xdebugMode,
            PHPStanRunMode::CollectPhpRenderPoints,
        );

        $result = new TwigStanAnalysisResult();

        foreach ($analysisResult->notFileSpecificErrors as $fileSpecificError) {
            $result = $result->withFileSpecificError($fileSpecificError);

            $errorOutput->writeln(sprintf('<error>Error</error> %s', $fileSpecificError));
        }

        if ($analysisResult->exitCode !== 0) {
            throw new LogicException('PHPStan exited with a non-zero exit code.');
        }

        if ($analysisResult->notFileSpecificErrors !== []) {
            return $result;
        }

        $templateContext = $this->templateContextFactory->create($analysisResult);

        $run = $this->executeRun(
            $output,
            $errorOutput,
            $twigFileNames,
            $compilationDirectory,
            $flatteningDirectory,
            $scopeInjectionDirectory,
            $templateContext,
            $debugMode,
            $xdebugMode,
        );

        $result = $result->withRun($run);

        $changedTemplates = [];
        $templateContext = $templateContext->merge($run->contextAfter, $changedTemplates);

        $result = $result->withContext($templateContext);

        // Filter out templates that do not exist.
        $changedTemplates = array_values(array_filter(
            $changedTemplates,
            fn($template) => $this->filesystem->exists($template),
        ));

        $errors = $run->errors;

        if ($changedTemplates !== []) {
            $output->writeln('Found new template context in Twig templates...');

            $output->writeln('Recompiling templates...');

            // Remove errors for the changed templates
            $errors = (new ErrorFilter(
                array_map(
                    fn($template) => IgnoreError::path($template),
                    $changedTemplates,
                ),
            ))->filter($run->errors);

            $run = $this->executeRun(
                $output,
                $errorOutput,
                $changedTemplates,
                $compilationDirectory,
                $flatteningDirectory,
                $scopeInjectionDirectory,
                $templateContext,
                $debugMode,
                $xdebugMode,
                2,
            );

            $result = $result->withRun($run);

            $errors = [...$errors, ...$run->errors];
        }

        // Ignore errors for abstract templates
        $abstractTemplates = $this->metadataRegistry->getAbstractTemplates();

        $errors = (new ErrorFilter([
            ...array_map(
                fn($template) => IgnoreError::path($template),
                $abstractTemplates,
            ),
            new IgnoreByIdentifierWhenSourceLocationHasPrevious([
                'function.alreadyNarrowedType',
                'function.impossibleType',
            ]),
            IgnoreError::identifier('isset.variable'),

            // It's perfectly fine to do `a == b ? 'yes' : 'no'` in Twig.
            IgnoreError::identifier('equal.notAllowed'),

            // It's perfectly fine to do `a != b ? 'no' : 'yes'` in Twig.
            IgnoreError::identifier('notEqual.notAllowed'),

            // It's perfectly fine to do `if(var)` in Twig.
            IgnoreError::identifier('if.condNotBoolean'),

            IgnoreError::identifier('ternary.condNotBoolean'),

            IgnoreError::identifier('booleanAnd.leftNotBoolean'),

            // It's perfectly fine to do `var ?: default` in Twig.
            IgnoreError::identifier('ternary.shortNotAllowed'),

            // The context is backed up before a loop and restored after it.
            // Therefore this is a non-issue in Twig templates.
            IgnoreError::identifier('foreach.valueOverwrite'),

            // These identifiers don't make sense for compiled Twig templates.
            IgnoreError::identifier('missingType.checkedException'),
            IgnoreError::identifier('missingType.parameter'),
            IgnoreError::identifier('missingType.return'),
            IgnoreError::identifier('method.missingOverride'),
            IgnoreError::identifier('return.unusedType'),

            // We cannot guarantee that a short arrow closure uses the context/macros/blocks variable.
            IgnoreError::messageAndIdentifier('#Anonymous function has an unused use \$context\.#', 'closure.unusedUse'),
            IgnoreError::messageAndIdentifier('#Anonymous function has an unused use \$macros\.#', 'closure.unusedUse'),
            IgnoreError::messageAndIdentifier('#Anonymous function has an unused use \$blocks#', 'closure.unusedUse'),

            // When the variable that is passed does not exist, this produces an error.
            IgnoreError::messageAndIdentifier('#CoreExtension::ensureTraversable#', 'argument.templateType'),

            // The context can contain anything, so we don't want to be strict here.
            IgnoreError::messageAndIdentifier('#Method __TwigTemplate_\w+::\w+\(\) has parameter#', 'missingType.iterableValue'),
            IgnoreError::messageAndIdentifier('#Method __TwigTemplate_\w+::\w+\(\) has parameter#', 'missingType.generics'),
            IgnoreError::messageAndIdentifier('#Method __TwigTemplate_\w+::\w+\(\) has parameter#', 'parameter.deprecatedClass'),
            IgnoreError::messageAndIdentifier('#Parameter \$context of method __TwigTemplate_\w+::\w+\(\) has typehint#', 'parameter.deprecatedClass'),

            // We cannot guarantee that the property will be used in the compiled template.
            IgnoreError::messageAndIdentifier('#Property __TwigTemplate_\w+::\$source is never read, only written\.#', 'property.onlyWritten'),

            // This happens because the parent method accepts an array.
            IgnoreError::messageAndIdentifier("#Parameter .* of method __TwigTemplate_\w+::doDisplay\(\) should be contravariant with parameter#", 'method.childParameterType'),

            // Currently Dynamic Inheritance is not (yet) supported. Ignoring the errors for now.
            // @see https://github.com/twigstan/twigstan/issues/6
            IgnoreError::messageAndIdentifier('#Access to an undefined property __TwigTemplate_\w+::\$blocks\.#', 'property.notFound'),
            IgnoreError::messageAndIdentifier('#Call to an undefined method __TwigTemplate_\w+::getParent\(\)\.#', 'method.notFound'),

            // @see https://github.com/twigphp/Twig/pull/4415
            IgnoreError::messageAndIdentifier('#Cannot call method unwrap\(\) on Twig\\\Template\|Twig\\\TemplateWrapper\|false\.#', 'method.nonObject'),
        ]))->filter($errors);

        foreach ($abstractTemplates as $abstractTemplate) {
            // We only want to error when an abstract template is rendered from PHP.
            $found = array_any(
                $templateContext->getByTemplate($abstractTemplate),
                function ($context) {
                    foreach ($context[0] as $sourceLocation) {
                        if (str_ends_with($sourceLocation->fileName, '.twig')) {
                            continue;
                        }

                        return true;
                    }

                    return false;
                },
            );

            if ( ! $found) {
                continue;
            }

            $errors[] = new Error(
                'Template is marked as abstract but is rendered directly.',
                sourceLocation: new SourceLocation($abstractTemplate, 0),
            );
        }

        // Transform PHPStan errors to TwigStan errors
        $errors = $this->errorFilter->filter($errors);
        $errors = $this->errorCollapser->collapse($errors);
        $errors = $this->errorTransformer->transform($errors);

        if ($this->onlyAnalyzeTemplatesWithContext) {
            // Filter out errors for templates that don't have a render point.
            $errors = array_values(array_filter($errors, function ($error) use ($templateContext, $errorOutput) {
                if ($error->twigFile === null) {
                    return true;
                }

                $hasRenderPoint = $templateContext->hasTemplate($error->twigFile);

                if ( ! $hasRenderPoint) {
                    $errorOutput->writeln(
                        sprintf('Ignoring template "%s" because it does not has any render points.', $error->twigFile),
                        OutputInterface::VERBOSITY_VERY_VERBOSE,
                    );
                }

                return $hasRenderPoint;
            }));
        }

        if ($generateBaselineFile === null) {
            $errors = $this->baselineErrorFilter->filter($errors);
        }

        $analysisResult = new PHPStanAnalysisResult(
            $analysisResult->exitCode,
            $errors,
            $analysisResult->collectedData,
            $analysisResult->notFileSpecificErrors,
        );

        if ($generateBaselineFile !== null) {
            $errorsCount = 0;

            /**
             * @var array<string, BaselineError> $baselineErrors
             */
            $baselineErrors = [];
            foreach ($errors as $error) {
                if ( ! $error->canBeIgnored) {
                    continue;
                }

                if ($error->twigFile === null) {
                    throw new LogicException('Error without Twig file should not be present here.');
                }

                $errorsCount++;

                $key = $error->message . "\n" . $error->identifier . "\n" . $error->twigFile;

                if (array_key_exists($key, $baselineErrors)) {
                    $baselineErrors[$key]->increaseCount();

                    continue;
                }

                $baselineErrors[$key] = new BaselineError(
                    $error->message,
                    $error->identifier,
                    $error->twigFile,
                    1,
                );
            }

            $dumper = new PhpBaselineDumper();

            $this->filesystem->dumpFile(
                $generateBaselineFile,
                $dumper->dump(
                    array_values($baselineErrors),
                    Path::getDirectory($generateBaselineFile),
                ),
            );

            $output->writeln(sprintf(
                'Baseline generated with %d %s in %s.',
                $errorsCount,
                $errorsCount === 1 ? 'error' : 'errors',
                Path::makeRelative($generateBaselineFile, $this->currentWorkingDirectory),
            ));

            if ($this->baselineFile === null) {
                $output->writeln('');

                $output->writeln('Make sure to add the following to your configuration file:');
                $output->writeln(sprintf(
                    "  ->baselineFile(__DIR__ . '/%s')",
                    Path::makeRelative($generateBaselineFile, Path::getDirectory($this->configurationFile)),
                ));

                $output->writeln('');
            }

            return $result;
        }

        foreach ($analysisResult->notFileSpecificErrors as $fileSpecificError) {
            $result = $result->withFileSpecificError($fileSpecificError);

            $errorOutput->writeln(sprintf('<error>Error</error> %s', $fileSpecificError));
        }

        foreach ($analysisResult->errors as $error) {
            $renderPoints = [];

            if ($error->sourceLocation !== null) {
                foreach ($templateContext->getByTemplate($error->sourceLocation->last()->fileName) as [$sourceLocation, $context]) {
                    $renderPoints[] = new RenderPoint(
                        $sourceLocation,
                        $context,
                    );
                }
            }

            $result = $result->withError(
                new TwigStanError(
                    $error->message,
                    $error->identifier,
                    $error->tip,
                    $error->phpFile,
                    $error->phpLine,
                    $error->sourceLocation,
                    $renderPoints,
                ),
            );
        }

        return $result;
    }

    private function linkify(string $fileName, string $relativeFileName, int $lineNumber): string
    {
        if ($this->editorUrl === null) {
            return sprintf(
                '%s:%d',
                $relativeFileName,
                $lineNumber,
            );
        }

        return sprintf(
            '<href=%s>%s:%d</>',
            str_replace(
                ['%relFile%', '%file%', '%line%'],
                [$relativeFileName, $fileName, (string) $lineNumber],
                $this->editorUrl,
            ),
            $relativeFileName,
            $lineNumber,
        );
    }

    /**
     * @param list<string> $twigFileNames
     *
     * @throws Throwable
     */
    private function compileTemplates(
        OutputInterface $output,
        OutputInterface $errorOutput,
        array $twigFileNames,
        string $compilationDirectory,
        TemplateContext $templateContext,
        bool $debugMode,
        int $run,
    ): CompilationResultCollection {
        $count = count($twigFileNames);
        $output->writeln(sprintf('Compiling %d templates...', $count));

        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();

        $compilationResults = new CompilationResultCollection();
        foreach ($twigFileNames as $twigFile) {
            try {
                $compilationResults = $compilationResults->with(
                    $this->twigCompiler->compile(
                        $twigFile,
                        $compilationDirectory,
                        $templateContext,
                        $run,
                    ),
                );
            } catch (Throwable $error) {
                $progressBar->clear();
                $errorOutput->writeln(
                    sprintf(
                        'Error compiling %s: %s',
                        Path::makeRelative($twigFile, $this->currentWorkingDirectory),
                        $error->getMessage(),
                    ),
                );

                if ($debugMode) {
                    throw $error;
                }
            } finally {
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $progressBar->clear();

        return $compilationResults;
    }

    /**
     * @param list<string> $twigFileNames
     * @param positive-int $run
     * @throws Throwable
     */
    private function executeRun(
        OutputInterface $output,
        OutputInterface $errorOutput,
        array $twigFileNames,
        string $compilationDirectory,
        string $flatteningDirectory,
        string $scopeInjectionDirectory,
        TemplateContext $templateContext,
        bool $debugMode,
        bool $xdebugMode,
        int $run = 1,
    ): TwigStanRun {
        if ($run > 1) {
            $output->writeln(sprintf('Run %d', $run));
        }

        $twigFileNamesToAnalyze = $twigFileNames;

        $output->writeln(sprintf('Finding dependencies for %d templates...', count($twigFileNamesToAnalyze)));

        // Maybe this should be done using a graph later.
        $dependencies = $this->dependencyFinder->getDependencies($twigFileNames);
        $twigFileNames = array_values(array_unique([...$dependencies, ...$twigFileNames]));
        $twigFileNames = $this->dependencySorter->sortByDependencies($twigFileNames);

        $count = count($twigFileNames);
        $dependencyCount = $count - count($twigFileNamesToAnalyze);
        $output->writeln(sprintf('Found %d %s...', $dependencyCount, $dependencyCount === 1 ? 'dependency' : 'dependencies'));

        $compilationResults = $this->compileTemplates(
            $output,
            $errorOutput,
            $twigFileNames,
            $compilationDirectory,
            $templateContext,
            $debugMode,
            $run,
        );

        $output->writeln(sprintf('Flattening %d templates...', $compilationResults->count()));

        $flatteningResults = $this->twigFlattener->flatten(
            $compilationResults,
            $flatteningDirectory,
            $run,
        );

        $output->writeln('Collecting scopes from Twig...');

        $analysisResult1 = $this->phpStanRunner->run(
            $output,
            $errorOutput,
            $this->environmentLoader,
            [],
            [Path::join($flatteningDirectory, (string) $run)],
            $debugMode,
            $xdebugMode,
            PHPStanRunMode::CollectTwigBlockContexts,
        );

        $result = new TwigStanAnalysisResult();

        foreach ($analysisResult1->notFileSpecificErrors as $fileSpecificError) {
            $result = $result->withFileSpecificError($fileSpecificError);

            $errorOutput->writeln(sprintf('<error>Error</error> %s', $fileSpecificError));
        }

        if ($analysisResult1->exitCode !== 0) {
            throw new LogicException('PHPStan exited with a non-zero exit code.');
        }

        if ($analysisResult1->notFileSpecificErrors !== []) {
            throw new LogicException('PHPStan exicted with not file specific errors.');
        }

        $output->writeln('Injecting scope into templates...');

        $scopeInjectionResults = $this->twigScopeInjector->inject(
            $analysisResult1->collectedData,
            $flatteningResults,
            $scopeInjectionDirectory,
            $run,
        );

        $output->writeln('Analyzing templates');

        $analysisResult2 = $this->phpStanRunner->run(
            $output,
            $errorOutput,
            $this->environmentLoader,
            [],
            [Path::join($scopeInjectionDirectory, (string) $run)],
            $debugMode,
            $xdebugMode,
            PHPStanRunMode::AnalyzeTwigTemplates,
        );

        $newTemplateContext = $this->templateContextFactory->create($analysisResult2);

        $errors = $this->errorToSourceFileMapper->map($scopeInjectionResults, $analysisResult2->errors);

        return new TwigStanRun(
            $run,
            $templateContext,
            $newTemplateContext,
            $errors,
            $compilationResults,
            $flatteningResults,
            $scopeInjectionResults,
            [
                PHPStanRunMode::CollectTwigBlockContexts->value => $analysisResult1,
                PHPStanRunMode::AnalyzeTwigTemplates->value => $analysisResult2,
            ],
        );
    }
}

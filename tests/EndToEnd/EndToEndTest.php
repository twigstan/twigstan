<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd;

use Nette\DI\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Throwable;
use Twig\Environment;
use Twig\NodeTraverser;
use TwigStan\Application\ContainerFactory;
use TwigStan\Application\PHPStanRunner;
use TwigStan\Processing\Compilation\CompilationResultCollection;
use TwigStan\Processing\Compilation\Parser\TwigNodeParser;
use TwigStan\Processing\Compilation\TwigCompiler;
use TwigStan\Processing\Flattening\TwigFlattener;
use TwigStan\Processing\ScopeInjection\TwigScopeInjector;
use TwigStan\Rules\DataProviderHelper;
use TwigStan\Testing\ExpectErrorNodeVisitor;
use TwigStan\Twig\Metadata\MetadataAnalyzer;
use TwigStan\Twig\Metadata\MetadataRegistry;

class EndToEndTest extends TestCase
{
    private string $transformedDirectory;
    private string $optimizedDirectory;
    private string $analyzeDirectory;
    private Container $twigStanContainer;
    private BufferedOutput $output;
    private BufferedOutput $errorOutput;
    private MetadataAnalyzer $metadataAnalyzer;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->transformedDirectory = Path::normalize(sys_get_temp_dir() . '/twigstan-transformed');
        $this->optimizedDirectory = Path::normalize(sys_get_temp_dir() . '/twigstan-optimized');
        $this->analyzeDirectory = Path::normalize(sys_get_temp_dir() . '/twigstan-analyze');

        $this->filesystem = new Filesystem();
        $this->filesystem->remove($this->transformedDirectory);
        $this->filesystem->remove($this->optimizedDirectory);
        $this->filesystem->remove($this->analyzeDirectory);
        $this->filesystem->mkdir($this->transformedDirectory);
        $this->filesystem->mkdir($this->optimizedDirectory);
        $this->filesystem->mkdir($this->analyzeDirectory);

        $containerFactory = new ContainerFactory(
            dirname(__DIR__, 2),
            __DIR__ . '/../twigstan.neon',
        );
        $this->twigStanContainer = $containerFactory->create(sys_get_temp_dir() . '/twigstan-e2e');
        $this->metadataAnalyzer = $this->twigStanContainer->getByType(MetadataAnalyzer::class);

        $this->output = new BufferedOutput();
        $this->errorOutput = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->transformedDirectory);
        $this->filesystem->remove($this->optimizedDirectory);
        $this->filesystem->remove($this->analyzeDirectory);
    }

    protected function onNotSuccessfulTest(Throwable $t): never
    {
        echo $this->output->fetch();
        echo $this->errorOutput->fetch();

        throw $t;
    }

    #[DataProvider('provideCases')]
    public function testCases(string $twigFile): void
    {
        /**
         * @var TwigNodeParser $twigNodeParser
         */
        $twigNodeParser = $this->twigStanContainer->getByType(TwigNodeParser::class);
        $ast = $twigNodeParser->parse($twigFile);

        $visitor = new ExpectErrorNodeVisitor($ast->getSourceContext()->getName());
        $traverser = new NodeTraverser(
            $this->twigStanContainer->getByType(Environment::class),
            [$visitor],
        );
        $traverser->traverse($ast);

        /**
         * @var TwigCompiler $twigCompiler
         */
        $twigCompiler = $this->twigStanContainer->getByType(TwigCompiler::class);
        $transformResult = $twigCompiler->compile($ast, $this->transformedDirectory);
        $mapping = new CompilationResultCollection(
            $transformResult,
            ...$this->findDependants($transformResult->twigFileName),
        );

        /**
         * @var TwigFlattener $twigFlattener
         */
        $twigFlattener = $this->twigStanContainer->getByType(TwigFlattener::class);
        $transformResult = $twigFlattener->flatten($mapping, $transformResult, $this->optimizedDirectory);
        $mapping = $mapping->with($transformResult);

        /**
         * @var PHPStanRunner $phpStanRunner
         */
        $phpStanRunner = $this->twigStanContainer->getByType(PHPStanRunner::class);

        // Collect scope context before every yieldBlock call
        $analysisResult = $phpStanRunner->run(
            $this->output,
            $this->errorOutput,
            __DIR__ . '/../twigstan.neon',
            __DIR__ . '/../twig-loader.php',
            [$this->optimizedDirectory],
            extension_loaded('xdebug'),
            extension_loaded('xdebug'),
            collectOnly: true,
        );

        /**
         * @var TwigScopeInjector $twigScopeInjector
         */
        $twigScopeInjector = $this->twigStanContainer->getByType(TwigScopeInjector::class);
        $mapping = $twigScopeInjector->inject($analysisResult->collectedData, $mapping, $this->optimizedDirectory);

        $analysisResult = $phpStanRunner->run(
            $this->output,
            $this->output,
            __DIR__ . '/../twigstan.neon',
            __DIR__ . '/../twig-loader.php',
            [
                $mapping->getByTwigFileName($ast->getSourceContext()->getName())->phpFile,
            ],
            extension_loaded('xdebug'),
            extension_loaded('xdebug'),
            $mapping,
        );

        self::assertSame(
            $visitor->expectedErrors,
            array_map(
                function ($error) {
                    return sprintf('%s: %s', $error->sourceLocation, $error->message);
                },
                $analysisResult->errors,
            ),
            var_export($analysisResult->errors, true),
        );
    }

    public static function provideCases(): iterable
    {
        yield from DataProviderHelper::createCasesFromDirectory(__DIR__, '/^[^_][\w\.]*\.twig$/');
    }

    private function findDependants(string $twigFileName): CompilationResultCollection
    {
        /**
         * @var MetadataRegistry $metadataRegistry
         */
        $metadataRegistry = $this->twigStanContainer->getByType(MetadataRegistry::class);

        /**
         * @var TwigNodeParser $twigNodeParser
         */
        $twigNodeParser = $this->twigStanContainer->getByType(TwigNodeParser::class);

        /**
         * @var TwigCompiler $twigCompiler
         */
        $twigCompiler = $this->twigStanContainer->getByType(TwigCompiler::class);

        $mapping = new CompilationResultCollection();

        $metadata = $metadataRegistry->getMetadata($twigFileName);
        if ($metadata->hasParents()) {
            foreach ($metadata->parents as $parent) {
                $ast = $twigNodeParser->parse($parent);
                $transformResult = $twigCompiler->compile($ast, $this->transformedDirectory);
                $mapping = $mapping->with(
                    $transformResult,
                    ...$this->findDependants($transformResult->twigFileName),
                );
            }
        }

        return $mapping;
    }
}

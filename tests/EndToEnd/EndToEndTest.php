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
use TwigStan\PHPStan\Analysis\AnalysisResultFromJsonReader;
use TwigStan\Rules\DataProviderHelper;
use TwigStan\Testing\ExpectErrorNodeVisitor;
use TwigStan\Twig\Parser\TwigNodeParser;
use TwigStan\Twig\Transforming\TwigTransformer;

class EndToEndTest extends TestCase
{
    private string $tempDir;
    private string $analysisResultJsonFile;
    private Container $twigStanContainer;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->tempDir = Path::normalize(sys_get_temp_dir() . '/twigstan');
        $this->analysisResultJsonFile = tempnam(sys_get_temp_dir(), 'twigstan-');

        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
        $filesystem->remove($this->analysisResultJsonFile);
        $filesystem->mkdir($this->tempDir);

        $containerFactory = new ContainerFactory(
            dirname(__DIR__, 2),
            __DIR__ . '/../twigstan.neon',
        );
        $this->twigStanContainer = $containerFactory->create(sys_get_temp_dir() . '/twigstan-e2e');

        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
        $filesystem->remove($this->analysisResultJsonFile);
    }

    protected function onNotSuccessfulTest(Throwable $t): never
    {
        echo $this->output->fetch();

        throw $t;
    }

    #[DataProvider('provideCases')]
    public function testCases(string $twigFile): void
    {
        $twigNodeParser = $this->twigStanContainer->getByType(TwigNodeParser::class);
        $ast = $twigNodeParser->parse($twigFile);

        $visitor = new ExpectErrorNodeVisitor();
        $traverser = new NodeTraverser(
            $this->twigStanContainer->getByType(Environment::class),
            [$visitor],
        );
        $traverser->traverse($ast);

        $twigTransformer = $this->twigStanContainer->getByType(TwigTransformer::class);
        $transformResult = $twigTransformer->transform($ast, $this->tempDir);
        $mapping[$transformResult->phpFile] = $transformResult;

        $phpStanRunner = $this->twigStanContainer->getByType(PHPStanRunner::class);
        $phpStanRunner->run(
            $this->output,
            $this->output,
            __DIR__ . '/../twigstan.neon',
            __DIR__ . '/../twig-loader.php',
            $this->tempDir,
            $this->analysisResultJsonFile,
            true,
            false,
        );

        $analysisResultFromJsonReader = $this->twigStanContainer->getByType(AnalysisResultFromJsonReader::class);
        $analysisResult = $analysisResultFromJsonReader->read($this->analysisResultJsonFile, $mapping);

        self::assertSame(
            $visitor->expectedErrors,
            array_map(
                fn($error) => sprintf('%02d: %s', $error->twigLine, $error->message),
                $analysisResult->errors,
            ),
        );
    }

    public static function provideCases(): iterable
    {
        yield from DataProviderHelper::createCasesFromDirectory(__DIR__, '/^[^_][\w\.]*\.twig$/');
    }
}

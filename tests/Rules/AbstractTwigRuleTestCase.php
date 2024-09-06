<?php

declare(strict_types=1);

namespace TwigStan\Rules;

use Nette\DI\Container;
use PHPStan\Analyser\Error;
use PHPStan\Testing\RuleTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use TwigStan\Application\ContainerFactory;
use TwigStan\Processing\Compilation\CompilationResult;
use TwigStan\Processing\Compilation\TwigCompiler;

abstract class AbstractTwigRuleTestCase extends RuleTestCase
{
    private string $tempDir;
    private Container $twigStanContainer;

    public static function getAdditionalConfigFiles(): array
    {
        return [
            ...parent::getAdditionalConfigFiles(),
            __DIR__ . '/../config.php',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = Path::normalize(sys_get_temp_dir() . '/twigstan');

        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
        $filesystem->mkdir($this->tempDir);

        $containerFactory = new ContainerFactory(
            dirname(__DIR__, 2),
            __DIR__ . '/../twigstan.neon',
        );
        $this->twigStanContainer = $containerFactory->create(sys_get_temp_dir() . '/twigstan-e2e');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function gatherAnalyserErrors(array $files): array
    {
        $transformer = $this->twigStanContainer->getByType(TwigCompiler::class);

        /**
         * @var array<string, CompilationResult> $mapping
         */
        $mapping = [];

        foreach ($files as $file) {
            $transformResult = $transformer->transform($file, $this->tempDir);
            $mapping[$transformResult->phpFile] = $transformResult;
        }

        $actualErrors = parent::gatherAnalyserErrors(array_keys($mapping));

        return array_map(
            function (Error $error) use ($mapping) {
                return new Error(
                    $error->getMessage(),
                    $error->getFile(),
                    $error->getLine() !== null ? $mapping[$error->getFile()]->getSourceLocationForPhpLine($error->getLine()) : null,
                    $error->canBeIgnored(),
                    $error->getFilePath(),
                    $error->getTraitFilePath(),
                    $error->getTip(),
                    $error->getNodeLine() !== null ? $mapping[$error->getFile()]->getSourceLocationForPhpLine($error->getNodeLine()) : null,
                    $error->getNodeType(),
                    $error->getIdentifier(),
                    $error->getMetadata(),
                );
            },
            $actualErrors,
        );
    }
}

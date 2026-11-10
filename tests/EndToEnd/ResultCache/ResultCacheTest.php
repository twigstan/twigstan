<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\ResultCache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;

/**
 * PHPStan only saves its result cache when it analyses directories, so the render points are collected from the configured paths.
 */
final class ResultCacheTest extends TestCase
{
    public function test(): void
    {
        $container = ContainerFactory::fromFile(dirname(__DIR__, 3), __DIR__ . '/../../twigstan-paths.php')->create();
        $resultCache = __DIR__ . '/../../.twigstan/phpstan/collect-php-render-points.resultCache.php';
        (new Filesystem())->remove($resultCache);

        $command = $container->getByType(AnalyzeCommand::class);
        $cold = $command->analyze([], new BufferedOutput(), new BufferedOutput(), false, false, null);

        self::assertFileExists($resultCache);

        // PHPStan rebuilds its container when a configuration file is modified, and re-hashes modified files.
        $configurationFiles = glob(__DIR__ . '/../../.twigstan/phpstan.*.neon');
        $templateFiles = glob(__DIR__ . '/../../.twigstan/{flattening,scope-injection}/1/*.php', GLOB_BRACE);
        self::assertIsArray($configurationFiles);
        self::assertIsArray($templateFiles);
        $generatedFiles = [...$configurationFiles, ...$templateFiles];
        $modified = time() - 60;
        foreach ($generatedFiles as $file) {
            touch($file, $modified);
        }

        // The render points of the second run come from the result cache.
        $warm = $command->analyze([], new BufferedOutput(), new BufferedOutput(), false, false, null);

        self::assertNotEmpty($cold->context->context);
        self::assertEquals($cold->context, $warm->context);
        self::assertEquals($cold->errors, $warm->errors);

        clearstatcache();
        self::assertNotEmpty($generatedFiles);
        foreach ($generatedFiles as $file) {
            self::assertSame($modified, filemtime($file), sprintf('%s was written again.', $file));
        }
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\ErrorLocation;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;

/**
 * An error in a layout is reported once, for one of the templates that extend it. Which one must not depend on
 * where the project is checked out, otherwise a baseline generated on one machine does not match on another.
 */
final class ErrorLocationTest extends TestCase
{
    private const array FILES = [
        'templates/layout.twig' => "{% block body %}{{ app.user }}{% endblock %}\n",
        // The compiled pages only differ by the hash of their path, as their names are the same. They sort before the
        // compiled layout, otherwise the error is collapsed into the one of the layout, which is dropped because the
        // layout is not rendered.
        'templates/a/index.twig' => "{% extends 'layout.twig' %}\n",
        'templates/b/index.twig' => "{% extends 'layout.twig' %}\n",
        'src/Renderer.php' => <<<'PHP'
            <?php

            function render(Twig\Environment $twig): void
            {
                $twig->render('a/index.twig', []);
                $twig->render('b/index.twig', []);
            }
            PHP,
        'twig-loader.php' => <<<'PHP'
            <?php

            return new Twig\Environment(new Twig\Loader\FilesystemLoader(__DIR__ . '/templates'));
            PHP,
    ];

    private Filesystem $filesystem;

    /**
     * @var list<string>
     */
    private array $directories = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directories);
    }

    public function test(): void
    {
        [$aFirst, $bFirst] = $this->findDirectoriesWithOppositeCompiledFileOrder();

        self::assertSame(
            $this->analyzeAndGetErrorLocations($aFirst),
            $this->analyzeAndGetErrorLocations($bFirst),
        );
    }

    /**
     * The compiled templates are named after a hash of their absolute path, and PHPStan reports the errors sorted by file.
     *
     * @return array{string, string}
     */
    private function findDirectoriesWithOppositeCompiledFileOrder(): array
    {
        $base = Path::join(sys_get_temp_dir(), sprintf('twigstan-error-location-%s', bin2hex(random_bytes(4))));
        $this->filesystem->mkdir($base);
        $this->directories[] = $base;
        // The hash is of the real path, the temporary directory is a symlink on macOS.
        $base = (string) realpath($base);
        $aFirst = null;
        $bFirst = null;

        for ($i = 0; $i < 100; $i++) {
            $directory = Path::join($base, (string) $i);
            $isAFirst = strcmp(
                hash('crc32', Path::join($directory, 'templates/a/index.twig')),
                hash('crc32', Path::join($directory, 'templates/b/index.twig')),
            ) < 0;

            if ($isAFirst) {
                $aFirst ??= $directory;
            } else {
                $bFirst ??= $directory;
            }

            if ($aFirst !== null && $bFirst !== null) {
                return [$aFirst, $bFirst];
            }
        }

        throw new RuntimeException('Could not find directories with opposite compiled file order.');
    }

    /**
     * @return list<list<string>> The source locations of the errors, relative to the project directory, the last one is the template the error is reported for
     */
    private function analyzeAndGetErrorLocations(string $directory): array
    {
        foreach (self::FILES as $name => $source) {
            $this->filesystem->dumpFile(Path::join($directory, $name), $source);
        }

        $this->filesystem->dumpFile(Path::join($directory, 'twigstan.php'), sprintf(
            <<<'PHP'
                <?php

                return TwigStan\Config\TwigStanConfig::configure(__DIR__)
                    ->phpstanBinPath(%s)
                    ->phpstanConfigurationFile(%s)
                    ->twigEnvironmentLoader(__DIR__ . '/twig-loader.php')
                    ->twigPaths(__DIR__ . '/templates')
                    ->phpPaths(__DIR__ . '/src');
                PHP,
            var_export(Path::join(dirname(__DIR__, 3), 'vendor/bin/phpstan'), true),
            var_export(Path::join(dirname(__DIR__, 2), 'phpstan.neon'), true),
        ));

        $command = ContainerFactory::fromFile($directory, Path::join($directory, 'twigstan.php'))
            ->create()
            ->getByType(AnalyzeCommand::class);

        $output = new BufferedOutput();
        $errorOutput = new BufferedOutput();
        $result = $command->analyze([], $output, $errorOutput, false, false, null);

        self::assertNotEmpty($result->errors, $output->fetch() . $errorOutput->fetch());

        $locations = [];
        foreach ($result->errors as $error) {
            $errorLocations = [];
            foreach ($error->twigSourceLocation ?? [] as $sourceLocation) {
                $errorLocations[] = sprintf('%s:%d', Path::makeRelative($sourceLocation->fileName, $directory), $sourceLocation->lineNumber);
            }

            $locations[] = $errorLocations;
        }

        return $locations;
    }
}

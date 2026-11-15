<?php

declare(strict_types=1);

namespace TwigStan\Benchmarks\Support;

use PHPStan\Analyser\Analyser;
use PHPStan\Analyser\InternalError;
use PHPStan\DependencyInjection\ContainerFactory as PHPStanContainerFactory;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Output\NullOutput;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\Application\TwigStanAnalysisResult;
use TwigStan\Config\TwigStanConfig;
use TwigStan\DependencyInjection\ContainerFactory;

/**
 * A project with templates and the PHP code that renders them, analysed by TwigStan. Shared by the benchmarks, which
 * load it with require_once, because each case loads only its own bench file and the autoloader of an older commit
 * does not know classes added here.
 */
final class TwigStanProject
{
    private ?AnalyzeCommand $command = null;

    /**
     * @param array<string, string> $files contents by path relative to $directory, such as templates/page.twig or src/Controller.php
     */
    public function __construct(
        public readonly string $directory,
        array $files,
    ) {
        $files += [
            'twig-loader.php' => <<<'PHP'
                <?php

                declare(strict_types=1);

                use Twig\Environment;
                use Twig\Loader\FilesystemLoader;

                return new Environment(new FilesystemLoader(__DIR__ . '/templates'));
                PHP,
            // The classes of the project, which an application's autoloader would find.
            'phpstan.neon' => "parameters:\n\tlevel: 8\n\tscanDirectories:\n\t\t- src\n",
        ];

        foreach ($files as $path => $contents) {
            $file = $this->directory . '/' . $path;

            if ( ! is_dir(dirname($file)) && ! mkdir(dirname($file), 0o777, true)) {
                throw new RuntimeException(sprintf('Cannot create the directory of %s.', $file));
            }

            file_put_contents($file, $contents);
        }
    }

    /**
     * Runs the whole analysis. TwigStan runs PHPStan in other processes, which Tally does not count, so only what
     * TwigStan does itself is counted: finding, compiling, flattening and injecting templates, and reading the results.
     */
    public function analyze(): TwigStanAnalysisResult
    {
        $this->command ??= (new ContainerFactory(
            $this->directory,
            $this->directory . '/twigstan.php',
            TwigStanConfig::configure($this->directory)
                ->tempDirectory($this->directory . '/.twigstan')
                ->phpstanBinPath(dirname(__DIR__, 2) . '/vendor/bin/phpstan')
                ->phpstanConfigurationFile($this->directory . '/phpstan.neon')
                ->phpstanMemoryLimit(false)
                ->twigEnvironmentLoader($this->directory . '/twig-loader.php')
                ->twigPaths($this->directory . '/templates')
                ->phpPaths($this->directory . '/src')
                ->create(),
        ))->create()->getByType(AnalyzeCommand::class);

        return $this->command->analyze([], new NullOutput(), new NullOutput(), false, false, null);
    }

    /**
     * Prepares PHPStan to analyse the templates of the first run in this process, as TwigStan's last PHPStan process
     * does, after analyze() wrote them. The returned function analyses them and returns the number of errors.
     *
     * The template $warmup is analysed here, so that PHPStan's classes are loaded, and left out of the returned function,
     * which then analyses the other templates with empty caches.
     *
     * @return callable(): int
     */
    public function templateAnalyser(string $warmup): callable
    {
        $temp = $this->directory . '/.twigstan';
        $files = glob($temp . '/scope-injection/1/*.php');

        if ($files === false) {
            throw new RuntimeException('Cannot list the compiled templates.');
        }

        $templates = [];
        $warmupTemplates = [];
        foreach ($files as $file) {
            // Compiled templates are named after the template, followed by a hash of its path.
            if (str_starts_with(basename($file), $warmup . '.')) {
                $warmupTemplates[] = $file;
            } else {
                $templates[] = $file;
            }
        }

        if ($templates === [] || $warmupTemplates === []) {
            throw new RuntimeException('Run analyze() first, it compiles the templates.');
        }

        // Before 0.2.5, the runs shared one configuration file.
        $config = $temp . '/phpstan.analyze-twig-templates.1.neon';

        if ( ! is_file($config)) {
            $config = $temp . '/phpstan.analyze-twig-templates.neon';
        }

        // PHPStan's command adds the configuration of the level, see phpstan.neon above.
        $levelConfig = dirname((string) (new ReflectionClass(PHPStanContainerFactory::class))->getFileName(), 3) . '/conf/config.level8.neon';
        $container = (new PHPStanContainerFactory($this->directory))->create(
            $temp . '/phpstan',
            [$levelConfig, $config],
            [$temp . '/scope-injection/1'],
            usedLevel: '8',
        );

        foreach ($container->getParameter('bootstrapFiles') as $bootstrapFile) {
            require_once $bootstrapFile;
        }

        $analyser = $container->getByType(Analyser::class);
        $analyser->analyse($warmupTemplates, null, null, false, $warmupTemplates);

        return static function () use ($analyser, $templates): int {
            $result = $analyser->analyse($templates, null, null, false, $templates);

            if ($result->getInternalErrors() !== []) {
                throw new RuntimeException(implode("\n", array_map(fn(InternalError $error) => $error->getMessage(), $result->getInternalErrors())));
            }

            return count($result->getUnorderedErrors());
        };
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use InvalidArgumentException;
use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use TwigStan\Config\ConfigBuilder;
use TwigStan\Config\TwigStanConfig;

final readonly class ContainerFactory
{
    private string $rootDirectory;
    private string $currentWorkingDirectory;

    public function __construct(
        string $currentWorkingDirectory,
        private TwigStanConfig $configuration,
    ) {
        $this->rootDirectory = Path::canonicalize(dirname(__DIR__, 2));
        $this->currentWorkingDirectory = $currentWorkingDirectory;
    }

    public static function fromFile(string $currentWorkingDirectory, string $configurationFile): self
    {
        $configuration = include $configurationFile;

        if ($configuration instanceof ConfigBuilder) {
            $configuration = $configuration->create();
        }

        if ( ! $configuration instanceof TwigStanConfig) {
            throw new InvalidArgumentException(sprintf("Configuration file \"%s\" must return an instance of %s.\n", $configurationFile, TwigStanConfig::class));
        }

        return new self($currentWorkingDirectory, $configuration);
    }

    public function create(): Container
    {
        $tempDirectory = Path::makeAbsolute($this->configuration->tempDirectory ?? '.twigstan', $this->rootDirectory);

        $configurator = new Configurator();
        $configurator->setTempDirectory($tempDirectory);
        $configurator->addConfig(Path::join($this->rootDirectory, 'config/application.neon'));
        $configurator->addStaticParameters([
            'debugMode' => true,
            'rootDir' => $this->rootDirectory,
            'currentWorkingDirectory' => $this->currentWorkingDirectory,
            'tempDirectory' => $tempDirectory,
            'baselineErrors' => $this->configuration->baselineErrors,
            'baselineFile' => $this->configuration->baselineFile,
            'reportUnmatchedIgnoredErrors' => $this->configuration->reportUnmatchedIgnoredErrors,
            'phpstanConfigurationFile' => $this->configuration->phpstanConfigurationFile,
            'phpstanMemoryLimit' => $this->configuration->phpstanMemoryLimit,
            'twigEnvironmentLoader' => $this->configuration->twigEnvironmentLoader,
            'twigPaths' => $this->configuration->twigPaths,
            'twigExcludes' => $this->configuration->twigExcludes,
            'phpPaths' => $this->configuration->phpPaths,
            'phpExcludes' => $this->configuration->phpExcludes,
            'ignoreErrors' => $this->configuration->ignoreErrors,
        ]);
        $configurator->addDynamicParameters([
            'env' => getenv(),
        ]);

        $class = $configurator->loadContainer();

        $container = new $class();

        if ( ! $container instanceof Container) {
            throw new RuntimeException('Container is not an instance of Nette\DI\Container');
        }

        return $container;
    }
}

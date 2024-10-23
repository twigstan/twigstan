<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use InvalidArgumentException;
use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nette\DI\Definitions\Statement;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use TwigStan\Config\ConfigBuilder;
use TwigStan\Config\TwigStanConfig;
use TwigStan\Error\BaselineError;
use TwigStan\Error\IgnoreError;

final readonly class ContainerFactory
{
    private string $rootDirectory;

    public function __construct(
        private string $currentWorkingDirectory,
        private string $configurationFile,
        private TwigStanConfig $configuration,
    ) {
        $this->rootDirectory = Path::canonicalize(dirname(__DIR__, 2));
    }

    public static function fromFile(string $currentWorkingDirectory, string $configurationFile): self
    {
        $configuration = include $configurationFile;

        if ($configuration instanceof ConfigBuilder) {
            $configuration = $configuration->create();
        }

        if ( ! $configuration instanceof TwigStanConfig) {
            throw new InvalidArgumentException(sprintf('Configuration file "%s" must return an instance of %s.', $configurationFile, TwigStanConfig::class));
        }

        return new self($currentWorkingDirectory, $configurationFile, $configuration);
    }

    public function create(): Container
    {
        $configurator = new Configurator();
        $configurator->setTempDirectory($this->configuration->tempDirectory);
        $configurator->addConfig(Path::join($this->rootDirectory, 'config/application.neon'));
        $configurator->addStaticParameters([
            'debugMode' => true,
            'rootDir' => $this->rootDirectory,
            'currentWorkingDirectory' => $this->currentWorkingDirectory,
            'configurationFile' => $this->configurationFile,
            'tempDirectory' => $this->configuration->tempDirectory,
            'baselineFile' => $this->configuration->baselineFile,
            'reportUnmatchedIgnoredErrors' => $this->configuration->reportUnmatchedIgnoredErrors,
            'phpstanBinPath' => $this->configuration->phpstanBinPath,
            'phpstanConfigurationFile' => $this->configuration->phpstanConfigurationFile,
            'phpstanMemoryLimit' => $this->configuration->phpstanMemoryLimit,
            'twigEnvironmentLoader' => $this->configuration->twigEnvironmentLoader,
            'twigPaths' => $this->configuration->twigPaths,
            'twigExcludes' => $this->configuration->twigExcludes,
            'phpPaths' => $this->configuration->phpPaths,
            'phpExcludes' => $this->configuration->phpExcludes,
            'twigContextCollectors' => $this->configuration->twigContextCollectors,
            'baselineErrors' => array_map(
                fn(BaselineError $error) => new Statement(
                    BaselineError::class,
                    [
                        $error->message,
                        $error->identifier,
                        $error->path,
                    ],
                ),
                $this->configuration->baselineErrors,
            ),
            'ignoreErrors' => array_map(
                function (IgnoreError $error) {
                    return match (true) {
                        $error->message !== null && $error->identifier !== null && $error->path !== null => new Statement(
                            sprintf('%s::create', IgnoreError::class),
                            [$error->message, $error->identifier, $error->path],
                        ),
                        $error->message !== null && $error->identifier !== null && $error->path === null => new Statement(
                            sprintf('%s::messageAndIdentifier', IgnoreError::class),
                            [$error->message, $error->identifier],
                        ),
                        $error->message !== null && $error->identifier === null && $error->path === null => new Statement(
                            sprintf('%s::message', IgnoreError::class),
                            [$error->message],
                        ),
                        $error->message !== null && $error->identifier === null && $error->path !== null => new Statement(
                            sprintf('%s::messageAndPath', IgnoreError::class),
                            [$error->message, $error->path],
                        ),
                        $error->message === null && $error->identifier === null && $error->path !== null => new Statement(
                            sprintf('%s::path', IgnoreError::class),
                            [$error->path],
                        ),
                        $error->message === null && $error->identifier !== null && $error->path === null => new Statement(
                            sprintf('%s::identifier', IgnoreError::class),
                            [$error->identifier],
                        ),
                        $error->message === null && $error->identifier !== null && $error->path !== null => new Statement(
                            sprintf('%s::identifierAndPath', IgnoreError::class),
                            [$error->identifier, $error->path],
                        ),
                        default => throw new RuntimeException('Impossible to create IgnoreError statement.'),
                    };
                },
                $this->configuration->ignoreErrors,
            ),
        ]);
        $configurator->addDynamicParameters([
            'env' => getenv(),
        ]);

        $class = $configurator->loadContainer();

        $container = new $class();

        if ( ! $container instanceof Container) {
            throw new RuntimeException('Container is not an instance of Nette\DI\Container.');
        }

        return $container;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nette\Neon\Neon;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final readonly class ContainerFactory
{
    private string $rootDirectory;
    private string $currentWorkingDirectory;
    private string $configurationFile;

    public function __construct(
        string $currentWorkingDirectory,
        string $configurationFile,
    ) {
        $this->rootDirectory = Path::canonicalize(dirname(__DIR__, 2));
        $this->currentWorkingDirectory = $currentWorkingDirectory;
        $this->configurationFile = Path::makeAbsolute($configurationFile, $this->currentWorkingDirectory);
    }

    public function create(string $tempDirectory): Container
    {
        $filesystem = new Filesystem();
        $filesystem->mkdir($tempDirectory);

        $configuration = Neon::decodeFile($this->configurationFile);

        if (isset($configuration['parameters']['twigstan']['twig'])) {
            $configuration['parameters']['twigstan']['twig']['paths'] = array_map(
                fn(string $directory) => Path::makeAbsolute($directory, Path::getDirectory($this->configurationFile)),
                $configuration['parameters']['twigstan']['twig']['paths'] ?? [],
            );

            $configuration['parameters']['twigstan']['twig']['excludes'] = array_map(
                fn(string $directory) => Path::makeAbsolute($directory, Path::getDirectory($this->configurationFile)),
                $configuration['parameters']['twigstan']['twig']['excludes'] ?? [],
            );
        }

        if (isset($configuration['parameters']['twigstan']['php'])) {
            $configuration['parameters']['twigstan']['php']['paths'] = array_map(
                fn(string $directory) => Path::makeAbsolute($directory, Path::getDirectory($this->configurationFile)),
                $configuration['parameters']['twigstan']['php']['paths'] ?? [],
            );
            $configuration['parameters']['twigstan']['php']['excludes'] = array_map(
                fn(string $directory) => Path::makeAbsolute($directory, Path::getDirectory($this->configurationFile)),
                $configuration['parameters']['twigstan']['php']['excludes'] ?? [],
            );
        }

        if (isset($configuration['parameters']['twigstan']['environmentLoader'])) {
            $configuration['parameters']['twigstan']['environmentLoader'] = Path::makeAbsolute(
                $configuration['parameters']['twigstan']['environmentLoader'],
                Path::getDirectory($this->configurationFile),
            );
        }

        if (isset($configuration['includes'])) {
            foreach ($configuration['includes'] as $include) {
                if(pathinfo($include, PATHINFO_EXTENSION) === 'neon') {
                    $content = Neon::decodeFile(Path::makeAbsolute($include, Path::getDirectory($this->configurationFile)));
                    $ignoreErrors = $content['parameters']['twigstan']['ignoreErrors'];
                    $configuration['parameters']['twigstan']['ignoreErrors'][] = $ignoreErrors;
                }
            }

            $configuration['parameters']['twigstan']['ignoreErrors'] = array_merge(...$configuration['parameters']['twigstan']['ignoreErrors']);
        }

        $configurator = new Configurator();
        $configurator->addConfig(Path::join($this->rootDirectory, 'config/application.neon'));
        $configurator->addStaticParameters($configuration['parameters']);
        $configurator->setTempDirectory($tempDirectory);
        $configurator->addStaticParameters([
            'debugMode' => true,
            'rootDir' => $this->rootDirectory,
            'currentWorkingDirectory' => $this->currentWorkingDirectory,
            'tmpDir' => $tempDirectory,
        ]);
        $configurator->addDynamicParameters([
            'env' => getenv(),
        ]);

        $class = $configurator->loadContainer();

        $container = new $class();

        if (!$container instanceof Container) {
            throw new RuntimeException('Container is not an instance of Nette\DI\Container');
        }

        return $container;
    }
}

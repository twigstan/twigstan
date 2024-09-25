<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\DI\Container;
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

        $configurator = new Configurator(
            new LoaderFactory(
                $this->rootDirectory,
                $this->currentWorkingDirectory,
            ),
        );
        $configurator->addConfig(Path::join($this->rootDirectory, 'config/application.neon'));
        $configurator->addConfig($this->configurationFile);
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

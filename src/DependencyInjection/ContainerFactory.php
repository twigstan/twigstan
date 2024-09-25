<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Config\Loader;
use Nette\DI\Container;
use Nette\Schema\Processor;
use RuntimeException;
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

    public function create(): Container
    {
        $adapter = new RelativePathSupportingNeonAdapter(
            new NeonAdapter(),
            new SchemaFactory(),
            new Processor(),
        );

        $loader = new Loader();
        $loader->addAdapter('dist', $adapter);
        $loader->addAdapter('neon', $adapter);
        $loader->setParameters([
            'rootDir' => $this->rootDirectory,
            'currentWorkingDirectory' => $this->currentWorkingDirectory,
            'env' => getenv(),
        ]);

        $projectConfig = $loader->load($this->configurationFile);

        $configurator = new Configurator($loader);
        $configurator->setTempDirectory($projectConfig['parameters']['tempDir'] ?? Path::join(Path::getDirectory($this->configurationFile), '.twigstan'));
        $configurator->addConfig(Path::join($this->rootDirectory, 'config/application.neon'));
        $configurator->addConfig($projectConfig);
        $configurator->addStaticParameters([
            'debugMode' => true,
            'rootDir' => $this->rootDirectory,
            'currentWorkingDirectory' => $this->currentWorkingDirectory,
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

<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nette\Neon\Neon;
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
        if (isset($configuration['parameters']['twigstan']['directories'])) {
            $configuration['parameters']['twigstan']['directories'] = array_map(
                fn(string $directory) => Path::makeAbsolute($directory, Path::getDirectory($this->configurationFile)),
                $configuration['parameters']['twigstan']['directories'],
            );
        }
        if (isset($configuration['parameters']['twigstan']['excludes'])) {
            $configuration['parameters']['twigstan']['excludes'] = array_map(
                fn(string $directory) => Path::makeAbsolute($directory, Path::getDirectory($this->configurationFile)),
                $configuration['parameters']['twigstan']['excludes'],
            );
        }

        if (isset($configuration['parameters']['twigstan']['environmentLoader'])) {
            $configuration['parameters']['twigstan']['environmentLoader'] = Path::makeAbsolute(
                $configuration['parameters']['twigstan']['environmentLoader'],
                Path::getDirectory($this->configurationFile),
            );
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

        return new $class();
    }
}

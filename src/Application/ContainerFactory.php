<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Nette\Bootstrap\Configurator;
use Nette\DI\Container;
use Nette\Neon\Neon;
use Nette\Schema\Expect;
use Nette\Schema\Processor;
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

        $schema = Expect::structure([
            'includes' => Expect::listOf('string')->transform(
                fn(array $files) => array_map($this->makeAbsolute(...), $files),
            ),
            'parameters' => Expect::structure([
                'twigstan' => Expect::structure([
                    'php' => Expect::structure([
                        'paths' => Expect::listOf('string')->transform(
                            fn(array $directories) => array_map($this->makeAbsolute(...), $directories),
                        ),
                        'excludes' => Expect::listOf('string'),
                    ]),
                    'twig' => Expect::structure([
                        'paths' => Expect::listOf('string')->transform(
                            fn(array $directories) => array_map($this->makeAbsolute(...), $directories),
                        ),
                        'excludes' => Expect::listOf('string'),
                    ]),
                    'environmentLoader' => Expect::string()->transform($this->makeAbsolute(...)),
                ]),
            ]),
        ]);

        $processor = new Processor();
        $configuration = $processor->process($schema, $configuration);

        // See https://github.com/orgs/nette/discussions/1568
        $configuration = json_decode(json_encode($configuration, flags: JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

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

    private function makeAbsolute(string $path): string
    {
        return Path::makeAbsolute($path, Path::getDirectory($this->configurationFile));
    }
}

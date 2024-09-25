<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Config\Loader;
use Nette\Schema\Processor;

final readonly class LoaderFactory
{
    public function __construct(
        private string $rootDir,
        private string $currentWorkingDirectory,
    ) {}

    public function createLoader(): Loader
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
            'rootDir' => $this->rootDir,
            'currentWorkingDirectory' => $this->currentWorkingDirectory,
            'env' => getenv(),
        ]);

        return $loader;
    }

}

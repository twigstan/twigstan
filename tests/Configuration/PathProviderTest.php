<?php

namespace TwigStan\Configuration;

use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use TwigStan\Application\ContainerFactory;

class PathProviderTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $containerFactory = new ContainerFactory(__DIR__, __DIR__ . '/twigstan.neon');
        $this->container = $containerFactory->create(sys_get_temp_dir() . '/twigstan');
    }

    public function testGetPaths(): void
    {
        /** @var PathProvider $pathProvider */
        $pathProvider = $this->container->getByType(PathProvider::class);
        $phpPaths = $pathProvider->getPhpPaths();
        $twigPaths = $pathProvider->getTwigPaths();

        self::assertIsArray($phpPaths);
        self::assertIsArray($twigPaths);

        self::assertCount(2, $phpPaths);
        self::assertCount(2, $twigPaths);

        self::assertSame($phpPaths, $pathProvider->getPhpPaths());
        self::assertSame($twigPaths, $pathProvider->getTwigPaths());
    }
}

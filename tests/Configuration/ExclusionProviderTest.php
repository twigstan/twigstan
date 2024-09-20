<?php

namespace TwigStan\Configuration;

use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use TwigStan\Application\ContainerFactory;

class ExclusionProviderTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $containerFactory = new ContainerFactory(__DIR__, __DIR__ . '/twigstan.neon');
        $this->container = $containerFactory->create(sys_get_temp_dir() . '/twigstan');
    }

    public function testGetExclusion(): void
    {
        /** @var ExclusionProvider $exclusionProvider */
        $exclusionProvider = $this->container->getByType(ExclusionProvider::class);
        $phpExcludes = $exclusionProvider->getPhpExcludes();
        $twigExcludes = $exclusionProvider->getTwigExcludes();

        self::assertIsArray($phpExcludes);
        self::assertIsArray($twigExcludes);

        self::assertCount(2, $phpExcludes);
        self::assertCount(2, $twigExcludes);

        self::assertSame($phpExcludes, $exclusionProvider->getPhpExcludes());
        self::assertSame($twigExcludes, $exclusionProvider->getTwigExcludes());
    }
}

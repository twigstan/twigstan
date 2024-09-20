<?php

namespace TwigStan\Application;

use Nette\DI\Container;
use PHPUnit\Framework\TestCase;

class ContainerFactoryTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $containerFactory = new ContainerFactory(__DIR__, __DIR__ . '/../twigstan.neon');
        $this->container = $containerFactory->create(sys_get_temp_dir() . '/twigstan');
    }

    public function testCreateContainerCorrectly(): void
    {
        self::assertInstanceOf(Container::class, $this->container);
    }

    public function testDirectoriesHasBeenInjectedCorrectly(): void
    {
        $phpSection = $this->container->getParameter('twigstan')['php'];
        $twigSection = $this->container->getParameter('twigstan')['twig'];

        self::assertIsArray($phpSection);
        self::assertIsArray($twigSection);

        self::assertArrayHasKey('paths', $phpSection);
        self::assertArrayHasKey('excludes', $phpSection);
        self::assertArrayHasKey('paths', $twigSection);
        self::assertArrayHasKey('excludes', $twigSection);

        self::assertCount(2, $phpSection['paths']);
        self::assertCount(0, $phpSection['excludes']);
        self::assertCount(2, $twigSection['paths']);
        self::assertCount(0, $twigSection['excludes']);
    }
}

<?php

namespace TwigStan\Application;

use Nette\DI\Container;
use PHPUnit\Framework\TestCase;

//TODO Add more test for cover all needed parameters/services
class ContainerFactoryTest extends TestCase
{
    private ?Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $containerFactory = new ContainerFactory(__DIR__, __DIR__ . '/../twigstan.neon');
        $this->container = $containerFactory->create(sys_get_temp_dir() . '/twigstan');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->container = null;
    }

    public function testCreateContainerCorrectly(): void
    {
        self::assertInstanceOf(Container::class, $this->container);
    }

    public function testDirectoriesHasBeenInjectedCorrectly(): void
    {
        $directories = $this->container?->getParameter('twigstan')['directories'];

        self::assertIsArray($directories);

        self::assertCount(4, $directories);
    }
}

<?php

namespace TwigStan\Application;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeCommandTest extends TestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $containerFactory = new ContainerFactory(__DIR__, __DIR__ . '/twigstan.neon');
        $container = $containerFactory->create(sys_get_temp_dir() . '/twigstan');

        $analyzeCommand = $container->getByType(AnalyzeCommand::class);

        $this->commandTester = new CommandTester($analyzeCommand);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
    }
}

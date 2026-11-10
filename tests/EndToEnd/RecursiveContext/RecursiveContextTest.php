<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RecursiveContext;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;

final class RecursiveContextTest extends TestCase
{
    public function testRecursiveIncludesConvergeWithTypedApplicationAndParentContext(): void
    {
        $container = ContainerFactory::fromFile(dirname(__DIR__, 3), __DIR__ . '/../../twigstan-with-context.php')->create();
        $output = new class extends BufferedOutput {
            protected function doWrite(string $message, bool $newline): void
            {
                if (preg_match('/^Run (\d+)$/', $message, $matches) === 1 && (int) $matches[1] > 6) {
                    throw new RuntimeException('Recursive template analysis did not converge within 6 passes.');
                }

                parent::doWrite($message, $newline);
            }
        };
        $result = $container->getByType(AnalyzeCommand::class)->analyze([__DIR__], $output, new NullOutput(), true, false, null);

        self::assertSame([], $result->fileSpecificErrors);
        self::assertSame([], $result->errors);
        self::assertTrue($result->context->hasTemplate(__DIR__ . '/children.twig'));
        self::assertLessThanOrEqual(6, count($result->runs));
    }
}

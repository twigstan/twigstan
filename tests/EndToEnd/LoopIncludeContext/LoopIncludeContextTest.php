<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\LoopIncludeContext;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use Symfony\Component\Console\Output\NullOutput;
use TwigStan\Application\AnalyzeCommand;
use TwigStan\DependencyInjection\ContainerFactory;

final class LoopIncludeContextTest extends PHPStanTestCase
{
    public function testCollectsIncludesInsideLoopsWithLargeTypedContext(): void
    {
        $container = ContainerFactory::fromFile(
            dirname(__DIR__, 3),
            __DIR__ . '/../../twigstan-with-context.php',
        )->create();
        $command = $container->getByType(AnalyzeCommand::class);
        $typeResolver = self::getContainer()->getByType(TypeStringResolver::class);

        $result = $command->analyze([__DIR__], new NullOutput(), new NullOutput(), true, false, null);

        self::assertSame([], $result->fileSpecificErrors);
        self::assertTrue($result->context->hasTemplate(__DIR__ . '/list.twig'));
        $contexts = array_values($result->context->getByTemplate(__DIR__ . '/item.twig'));
        self::assertCount(
            2,
            $contexts,
            'Both includes inside the loop must retain their types when nested context snapshots exceed the array-shape budget.',
        );
        self::assertSame([2, 3], array_map(static fn($context) => $context[0]->lineNumber, $contexts));
        foreach ($contexts as [$sourceLocation, $context]) {
            $type = $typeResolver->resolve($context);
            $point = new ConstantStringType('point');
            self::assertTrue($type->hasOffsetValueType($point)->yes());
            self::assertTrue($type->getOffsetValueType($point)->isInteger()->yes());
            self::assertTrue((new ConstantStringType('known'))->equals($type->getOffsetValueType(new ConstantStringType('value'))));

            $inherited = $type->hasOffsetValueType(new ConstantStringType('inherited'));
            self::assertSame($sourceLocation->lineNumber === 2, $inherited->yes());
            self::assertSame($sourceLocation->lineNumber === 3, $inherited->no());

            if ($sourceLocation->lineNumber === 2) {
                self::assertTrue((new ConstantIntegerType(42))->equals($type->getOffsetValueType(new ConstantStringType('inherited'))));
            }
        }

        self::assertSame(["Variable 'missing' does not exist."], array_column($result->errors, 'message'));
    }
}

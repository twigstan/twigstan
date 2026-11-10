<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\TestCase;

final class PreserveContextTypeTest extends TestCase
{
    public function testPreservesKnownFieldsWhenLargeContextsAreMerged(): void
    {
        $metadata = ConstantArrayTypeBuilder::createEmpty();
        for ($i = 0; $i < 180; $i++) {
            $metadata->setOffsetValueType(new ConstantStringType('meta' . $i), new MixedType());
        }

        $contexts = [];
        foreach (['a', 'b'] as $branch) {
            $context = new ConstantArrayType(
                [new ConstantStringType('known'), new ConstantStringType('metadata'), new ConstantStringType('dynamic'), new ConstantStringType($branch)],
                [new IntegerType(), $metadata->getArray(), new MixedType(), new IntegerType()],
            );
            $contexts[] = $this->preserve($context);
        }

        $merged = TypeCombinator::union(...$contexts);
        self::assertSame('int', $merged->getOffsetValueType(new ConstantStringType('known'))->describe(VerbosityLevel::precise()));
    }

    public function testDoesNotInventFieldsForGenericContexts(): void
    {
        $context = new ArrayType(new StringType(), new MixedType());

        self::assertSame($context, $this->preserve($context));
    }

    private function preserve(Type $context): Type
    {
        $scope = $this->createMockForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        self::assertInstanceOf(Scope::class, $scope);
        self::assertInstanceOf(DependencyTracker::class, $scope);
        $scope->method('getType')->willReturn($context);
        $function = self::createStub(FunctionReflection::class);
        $function->method('getName')->willReturn('twigstan_preserve_context');
        $extension = new PreserveContextType();
        self::assertTrue($extension->isFunctionSupported($function));

        $type = $extension->getTypeFromFunctionCall(
            $function,
            new FuncCall(new Name('twigstan_preserve_context'), [new Arg(new Variable('context'))]),
            $scope,
        );
        self::assertNotNull($type);

        return $type;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\TypeNodeResolver;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\VerbosityLevel;

final class PreservedContextTypeNodeResolverTest extends PHPStanTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/phpstan.neon'];
    }

    public function testPreservesNestedShapesBeforeResolvingTheirUnion(): void
    {
        $metadata = implode(', ', array_map(static fn(int $i): string => 'meta' . $i . ': mixed', range(1, 180)));
        $context = 'array{known: int, metadata: array{' . $metadata . '}, dynamic: mixed, %s: int}';
        $annotation = 'array{parent: (' . sprintf($context, 'a') . ' | ' . sprintf($context, 'b') . ')}';
        $resolver = self::getContainer()->getByType(TypeStringResolver::class);
        $type = $resolver->resolve($annotation, new NameScope(null, [], '__TwigTemplate_Test'));
        $parent = $type->getOffsetValueType(new ConstantStringType('parent'));

        self::assertSame('int', $parent->getOffsetValueType(new ConstantStringType('known'))->describe(VerbosityLevel::precise()));
        self::assertTrue($type->getConstantArrays()[0]->isArrayShapePreserved());
        $application = $resolver->resolve($annotation, new NameScope(null, [], 'Application'));
        self::assertSame('mixed', $application->getOffsetValueType(new ConstantStringType('parent'))->getOffsetValueType(new ConstantStringType('known'))->describe(VerbosityLevel::precise()));
        self::assertFalse($resolver->resolve('array{known: int}', new NameScope(null, [], 'Application'))->getConstantArrays()[0]->isArrayShapePreserved());
    }

    public function testKeepsWideShapesAndExistingListAndOpenShapeSemantics(): void
    {
        $resolver = self::getContainer()->getByType(TypeStringResolver::class);
        $scope = new NameScope(null, [], '__TwigTemplate_Test');
        $fields = implode(', ', array_map(static fn(int $i): string => 'field' . $i . ': int', range(1, 300)));
        $wide = $resolver->resolve('array{' . $fields . '}', $scope);
        self::assertCount(300, $wide->getConstantArrays()[0]->getKeyTypes());
        self::assertTrue($resolver->resolve('list{int, string}', $scope)->isList()->yes());
        $open = $resolver->resolve('array{known: int, optional?: string, ...<string, mixed>}', $scope);
        self::assertSame('int', $open->getOffsetValueType(new ConstantStringType('known'))->describe(VerbosityLevel::precise()));
        self::assertTrue($open->hasOffsetValueType(new ConstantStringType('optional'))->maybe());
        self::assertTrue($open->hasOffsetValueType(new ConstantStringType('unknown'))->maybe());
    }
}

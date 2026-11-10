<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use TwigStan\Processing\ScopeInjection\ArrayShapeMerger;
use TwigStan\Twig\SourceLocation;

final class RecursiveContextNormalizerTest extends PHPStanTestCase
{
    public function testBoundsOnlyRecursiveAncestryAndKeepsDirectParentFields(): void
    {
        $container = self::getContainer();
        $normalizer = new RecursiveContextNormalizer($container->getByType(PhpDocParser::class), $container->getByType(Lexer::class), new ArrayShapeMerger());
        $annotation = 'array{known: int, _parent: array{known: string, _parent: array{known: bool}}, loop: array{index: int, parent: array{known: string, loop: array{index: int, parent: array{known: bool}}}}, data: array{_parent: array{known: bool}}}';
        $known = new TemplateContext(['child.twig' => ['first' => [new SourceLocation('parent.twig', 1), 'array{}']]]);
        $incoming = new TemplateContext(['parent.twig' => ['second' => [new SourceLocation('child.twig', 2), $annotation]]]);

        self::assertSame($incoming->context, $normalizer->normalize($incoming, new TemplateContext())->context);

        $normalized = $normalizer->normalize($incoming, $known);
        $context = $normalized->getUniqueContextByTemplate('parent.twig')[0];
        $type = $container->getByType(TypeStringResolver::class)->resolve($context);

        self::assertSame('int', $this->field($type, 'known')->describe(VerbosityLevel::precise()));
        self::assertSame('string', $this->field($type, 'loop', 'parent', 'known')->describe(VerbosityLevel::precise()));
        self::assertSame('int', $this->field($type, 'loop', 'parent', 'loop', 'index')->describe(VerbosityLevel::precise()));
        self::assertSame('array<mixed>', (string) $this->field($type, 'loop', 'parent', 'loop', 'parent')->toPhpDocNode());
        self::assertSame('array<mixed>', (string) $this->field($type, '_parent', '_parent')->toPhpDocNode());
        self::assertTrue($this->field($type, 'data', '_parent', 'known')->isBoolean()->yes());
        self::assertSame($normalized->context, $normalizer->normalize($normalized, $known)->context);
    }

    private function field(Type $type, string ...$keys): Type
    {
        foreach ($keys as $key) {
            $type = $type->getOffsetValueType(new ConstantStringType($key));
        }

        return $type;
    }

    public function testMergesRecursiveParentVariantsWithoutLosingTheirFieldTypes(): void
    {
        $container = self::getContainer();
        $normalizer = new RecursiveContextNormalizer($container->getByType(PhpDocParser::class), $container->getByType(Lexer::class), new ArrayShapeMerger());
        $annotation = "array{loop: (array{index: 0, parent: array{known: int, mode: 'a', other: 'first'}} | array{index: 1, parent: array{known: int, mode: 'b', other: 'second'}})}";
        $incoming = new TemplateContext(['recursive.twig' => ['point' => [new SourceLocation('recursive.twig', 1), $annotation]]]);
        $normalized = $normalizer->normalize($incoming, new TemplateContext());
        $type = $container->getByType(TypeStringResolver::class)->resolve($normalized->getUniqueContextByTemplate('recursive.twig')[0]);
        $parent = $this->field($type, 'loop', 'parent');

        self::assertCount(1, $parent->getConstantArrays());
        self::assertSame('int', $this->field($parent, 'known')->describe(VerbosityLevel::precise()));
        self::assertEqualsCanonicalizing(['a', 'b'], $this->field($parent, 'mode')->getConstantScalarValues());
    }
}

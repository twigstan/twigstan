<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayShapeMergerTest extends TestCase
{
    private ArrayShapeMerger $arrayShapeMerger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->arrayShapeMerger = new ArrayShapeMerger();
    }

    #[DataProvider('provideMergeCases')]
    #[DataProvider('provideMergeAppendCases')]
    #[Test]
    public function testMerge(ArrayShapeNode $left, ArrayShapeNode $right, ArrayShapeNode $expected, bool $append): void
    {
        self::assertEquals(
            $expected,
            $this->arrayShapeMerger->merge(
                $left,
                $right,
                $append,
            ),
        );
    }

    /**
     * @return iterable<array{ArrayShapeNode, ArrayShapeNode, ArrayShapeNode, false}>
     */
    public static function provideMergeCases(): iterable
    {
        yield [
            // left: array{firstName: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            // right: array{isAdmin: true}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin'),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            // result: array{firstName?: 'Jane', isAdmin?: true}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    true,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin'),
                    true,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            // right: array{firstName: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            // result: array{firstName: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            // right: array{firstName: 'John'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('John')),
                ),
            ]),
            // result: array{firstName: 'Jane'|'John'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Jane')),
                        new ConstTypeNode(new ConstExprStringNode('John')),
                    ]),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            // right: array{firstName?: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    true,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            // result: array{firstName?: 'Jane'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    true,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{isAdmin: true}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin'),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            // right: array{isAdmin: false}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin'),
                    false,
                    new ConstTypeNode(new ConstExprFalseNode()),
                ),
            ]),
            // result: array{isAdmin: true|false}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin'),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprTrueNode()),
                        new ConstTypeNode(new ConstExprFalseNode()),
                    ]),
                ),
            ]),
            false,
        ];
    }

    /**
     * @return iterable<array{ArrayShapeNode, ArrayShapeNode, ArrayShapeNode, true}>
     */
    public static function provideMergeAppendCases(): iterable
    {
        yield [
            // left: array{firstName: 'Jane', lastName: 'Doe'}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('lastName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Doe')),
                ),
            ]),
            // right: array{age?: int, lastName?: string, isAdmin: true}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('age'),
                    true,
                    new IdentifierTypeNode('int'),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('lastName'),
                    true,
                    new IdentifierTypeNode('string'),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin'),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            // result: array{firstName: 'Jane', lastName?: 'Doe'|string, age?: int, isAdmin: true}
            new ArrayShapeNode([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName'),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane')),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('lastName'),
                    true,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Doe')),
                        new IdentifierTypeNode('string'),
                    ]),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('age'),
                    true,
                    new IdentifierTypeNode('int'),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin'),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            true,
        ];
    }
}

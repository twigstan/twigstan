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
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // right: array{isAdmin: true}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            // result: array{firstName?: 'Jane', isAdmin?: true}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // right: array{firstName: 'Jane'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // result: array{firstName: 'Jane'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // right: array{firstName: 'John'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // result: array{firstName: 'Jane'|'John'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'|'John'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            // right: array{firstName: 'James'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('James', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // result: array{firstName: 'Jane'|'John'|'James'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('James', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'James'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('James', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // right: array{firstName: 'Jane'|'John'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            // result: array{firstName: 'James'|'Jane'|'John'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('James', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'|'John'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            // right: array{firstName: 'James'|'Jill'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('James', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('Jill', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            // result: array{firstName: 'James'|'Jane'|'John'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('John', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('James', ConstExprStringNode::SINGLE_QUOTED)),
                        new ConstTypeNode(new ConstExprStringNode('Jill', ConstExprStringNode::SINGLE_QUOTED)),
                    ]),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{firstName: 'Jane'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // right: array{firstName?: 'Jane'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // result: array{firstName?: 'Jane'}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            false,
        ];

        yield [
            // left: array{isAdmin: true}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            // right: array{isAdmin: false}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprFalseNode()),
                ),
            ]),
            // result: array{isAdmin: true|false}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin', ConstExprStringNode::SINGLE_QUOTED),
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
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('lastName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Doe', ConstExprStringNode::SINGLE_QUOTED)),
                ),
            ]),
            // right: array{age?: int, lastName?: string, isAdmin: true}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('age', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new IdentifierTypeNode('int'),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('lastName', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new IdentifierTypeNode('string'),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            // result: array{firstName: 'Jane', lastName?: 'Doe'|string, age?: int, isAdmin: true}
            ArrayShapeNode::createSealed([
                new ArrayShapeItemNode(
                    new ConstExprStringNode('firstName', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprStringNode('Jane', ConstExprStringNode::SINGLE_QUOTED)),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('lastName', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new UnionTypeNode([
                        new ConstTypeNode(new ConstExprStringNode('Doe', ConstExprStringNode::SINGLE_QUOTED)),
                        new IdentifierTypeNode('string'),
                    ]),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('age', ConstExprStringNode::SINGLE_QUOTED),
                    true,
                    new IdentifierTypeNode('int'),
                ),
                new ArrayShapeItemNode(
                    new ConstExprStringNode('isAdmin', ConstExprStringNode::SINGLE_QUOTED),
                    false,
                    new ConstTypeNode(new ConstExprTrueNode()),
                ),
            ]),
            true,
        ];
    }
}

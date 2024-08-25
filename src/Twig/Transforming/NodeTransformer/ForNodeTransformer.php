<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use Countable;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Unset_;
use TwigStan\Twig\Transforming\TransformScope;
use Twig\Node\ForNode;
use Twig\Node\Node as TwigNode;

/**
 * @implements TwigNodeTransformer<ForNode>
 */
final readonly class ForNodeTransformer implements TwigNodeTransformer
{
    public static function getType(): string
    {
        return ForNode::class;
    }

    public function transform(TwigNode $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): array
    {
        $stmts = [];

        $oldScope = $scope;

        $scope = $scope->enterScope();
        $contextVariable = $scope->getContextVariable();

        $stmts[] = new Expression(
            new Assign(
                new Variable($contextVariable),
                new Array_(
                    array_map(
                        fn(string $name) => new Expr\ArrayItem(
                            new Variable($name),
                            new String_($name),
                        ),
                        $oldScope->getAllVariableNames(),
                    ),
                    ['kind' => Array_::KIND_SHORT],
                ),
            ),
        );

        $stmts[] = new Expression(
            new Assign(
                new Variable('_parent'),
                new Variable($contextVariable),
            ),
        );
        $scope->assignVariableName('_parent');

        $stmts[] = new Expression(
            new Assign(
                new Variable('_iterated'),
                new ConstFetch(new Name('false')),
            ),
        );
        $scope->assignVariableName('_iterated');

        $stmts[] = new Expression(
            new Assign(
                new Variable('_seq'),
                $delegator->transform($node->getNode('seq'), $scope),
            ),
            attributes: ['startLine' => $node->getTemplateLine()],
        );
        $scope->assignVariableName('_seq');

        if ($node->getAttribute('with_loop')) {
            $stmts[] = new Expression(
                new Assign(
                    new Variable('loop'),
                    new Array_(
                        [
                            new Expr\ArrayItem(
                                new Variable($contextVariable),
                                new String_('parent'),
                            ),
                            new Expr\ArrayItem(
                                new LNumber(0),
                                new String_('index0'),
                            ),
                            new Expr\ArrayItem(
                                new LNumber(1),
                                new String_('index'),
                            ),
                            new Expr\ArrayItem(
                                new ConstFetch(new Name('true')),
                                new String_('first'),
                            ),
                        ],
                        ['kind' => Array_::KIND_SHORT],
                    ),
                ),
            );
            $scope->assignVariableName('loop');

            $stmts[] = new If_(
                new Expr\BinaryOp\BooleanOr(
                    new Expr\FuncCall(
                        new Name('is_array'),
                        [new Arg(
                            new Variable('_seq'),
                        )],
                    ),
                    new Expr\Instanceof_(
                        new Variable('_seq'),
                        new Name\FullyQualified(Countable::class),
                    ),
                ),
                [
                    'stmts' => [
                        new Expression(
                            new Assign(
                                new Variable('length'),
                                new Expr\FuncCall(
                                    new Name('count'),
                                    [new Arg(
                                        new Variable('_seq'),
                                    )],
                                ),
                            ),
                        ),
                        new Expression(
                            new Assign(
                                new ArrayDimFetch(
                                    new Variable('loop'),
                                    new String_('revindex0'),
                                ),
                                new Expr\BinaryOp\Minus(
                                    new Variable('length'),
                                    new LNumber(1),
                                ),
                            ),
                        ),
                        new Expression(
                            new Assign(
                                new ArrayDimFetch(
                                    new Variable('loop'),
                                    new String_('revindex'),
                                ),
                                new Variable('length'),
                            ),
                        ),
                        new Expression(
                            new Assign(
                                new ArrayDimFetch(
                                    new Variable('loop'),
                                    new String_('length'),
                                ),
                                new Variable('length'),
                            ),
                        ),
                        new Expression(
                            new Assign(
                                new ArrayDimFetch(
                                    new Variable('loop'),
                                    new String_('last'),
                                ),
                                new Identical(
                                    new LNumber(1),
                                    new Variable('length'),
                                ),
                            ),
                        ),
                    ],
                ],
            );
        }

        $subnodes = [];
        $subnodes['stmts'] = $delegator->transform($node->getNode('body'), $scope);
        $subnodes['stmts'][] = new Expression(
            new Assign(
                new Variable('_iterated'),
                new ConstFetch(new Name('true')),
            ),
        );

        if ($node->getAttribute('with_loop')) {
            $subnodes['stmts'][] = new Expression(
                new Expr\PostInc(
                    new ArrayDimFetch(
                        new Variable('loop'),
                        new String_('index0'),
                    ),
                ),
            );
            $subnodes['stmts'][] = new Expression(
                new Expr\PostInc(
                    new ArrayDimFetch(
                        new Variable('loop'),
                        new String_('index'),
                    ),
                ),
            );
            $subnodes['stmts'][] = new Expression(
                new Assign(
                    new ArrayDimFetch(
                        new Variable('loop'),
                        new String_('first'),
                    ),
                    new ConstFetch(new Name('false')),
                ),
            );

            $subnodes['stmts'][] = new If_(
                new Expr\Isset_([
                    new ArrayDimFetch(
                        new Variable('loop'),
                        new String_('revindex'),
                    ),
                ]),
                [
                    'stmts' => [
                        new Expression(
                            new PostDec(
                                new ArrayDimFetch(
                                    new Variable('loop'),
                                    new String_('revindex'),
                                ),
                            ),
                        ),
                    ],
                ],
            );

            $subnodes['stmts'][] = new If_(
                new Expr\Isset_([
                    new ArrayDimFetch(
                        new Variable('loop'),
                        new String_('revindex0'),
                    ),
                ]),
                [
                    'stmts' => [
                        new Expression(
                            new PostDec(
                                new ArrayDimFetch(
                                    new Variable('loop'),
                                    new String_('revindex0'),
                                ),
                            ),
                        ),
                        new Expression(
                            new Assign(
                                new ArrayDimFetch(
                                    new Variable('loop'),
                                    new String_('last'),
                                ),
                                new Identical(
                                    new LNumber(0),
                                    new ArrayDimFetch(
                                        new Variable('loop'),
                                        new String_('revindex0'),
                                    ),
                                ),
                            ),
                        ),
                    ],
                ],
            );
        }

        $subnodes['keyVar'] = $delegator->transform($node->getNode('key_target'), $scope);

        $scope->assignVariableName($node->getNode('key_target')->getAttribute('name'));

        $valueVar = $delegator->transform($node->getNode('value_target'), $scope);

        $scope->assignVariableName($node->getNode('value_target')->getAttribute('name'));

        $stmts[] = new Stmt\Foreach_(
            new Variable('_seq'),
            $valueVar,
            $subnodes,
            [
                'startLine' => $node->getTemplateLine(),
            ],
        );

        if ($node->hasNode('else')) {
            $stmts[] = new If_(
                new BooleanNot(
                    new Variable('_iterated'),
                ),
                [
                    'stmts' => $delegator->transform($node->getNode('else'), $scope, true),
                ],
                [
                    'startLine' => $node->getNode('else')->getTemplateLine(),
                ],
            );
        }

        $stmts[] = new Unset_(array_map(
            fn(string $name) => new Variable($name),
            array_unique([
                ...array_diff($scope->getAssignedVariableNames(), $oldScope->getAssignedVariableNames()),
                $node->getNode('value_target')->getAttribute('name'),
                $node->getNode('key_target')->getAttribute('name'),
            ]),
        ));

        $restore = array_intersect($scope->getAssignedVariableNames(), $oldScope->getAllVariableNames());
        foreach ($restore as $variableName) {
            $stmts[] = new Expression(
                new Assign(
                    new Variable($variableName),
                    new ArrayDimFetch(
                        new Variable($contextVariable),
                        new String_($variableName),
                    ),
                ),
            );
        }

        $stmts[] = new Unset_([
            new Variable($contextVariable),
        ]);

        return $stmts;
    }

}

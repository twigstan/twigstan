<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class InlineBlocksVisitor extends NodeVisitorAbstract
{
    /**
     * @param array<string, list<Node\Stmt>> $blocks
     */
    public function __construct(
        private array $blocks = [],
    ) {}

    /**
     * @return null|array<Node\Stmt\Expression>
     */
    public function leaveNode(Node $node): ?array
    {
        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        $node = $node->expr;
        if ( ! $node instanceof Node\Expr\YieldFrom) {
            return null;
        }

        $expr = $node->expr;
        if ( ! $expr instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $expr->name instanceof Node\Identifier) {
            return null;
        }

        if ($expr->name->name !== 'yieldBlock') {
            return null;
        }

        if ( ! $expr->var instanceof Node\Expr\MethodCall) {
            return null;
        }

        if ( ! $expr->var->name instanceof Node\Identifier) {
            return null;
        }

        if ($expr->var->name->name !== 'unwrap') {
            return null;
        }

        if (count($expr->args) !== 3) {
            return null;
        }

        $blockNameArg = $expr->args[0];

        if ( ! $blockNameArg instanceof Node\Arg) {
            return null;
        }

        if ( ! $blockNameArg->value instanceof Node\Scalar\String_) {
            return null;
        }

        $blockName = $blockNameArg->value->value;
        if ( ! isset($this->blocks[$blockName])) {
            return null;
        }

        return [
            new Node\Stmt\Expression(
                new Node\Expr\FuncCall(
                    new Node\Expr\Closure(
                        [
                            'stmts' => [
                                new Node\Stmt\Expression(
                                    new Node\Expr\FuncCall(
                                        new Node\Name('extract'),
                                        [
                                            new Node\Arg(
                                                new Node\Expr\Variable('context'),
                                            ),
                                        ],
                                    ),
                                ),
                                new Node\Stmt\Nop([
                                    'comments' => [new Comment\Doc(sprintf("/**\n * Start of block %s\n */", $blockName))],
                                ]),
                                ...$this->blocks[$blockName],
                                new Node\Stmt\Nop([
                                    'comments' => [new Comment(sprintf("/**\n * End of block %s\n */", $blockName))],
                                ]),
                            ],
                            'uses' => [
                                new Node\ClosureUse(
                                    new Node\Expr\Variable('context'),
                                ),
                                new Node\ClosureUse(
                                    new Node\Expr\Variable('blocks'),
                                ),
                            ],
                        ],
                    ),
                ),
                [
                    'comments' => [
                        new Comment("\n\n// @phpstan-ignore closure.unusedUse"),
                    ],
                ],
            ),
        ];
    }
}

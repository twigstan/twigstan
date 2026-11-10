<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Printer\Printer;

final class AddTypeCommentsToTemplateVisitor extends NodeVisitorAbstract
{
    public function __construct(
        private readonly ArrayShapeNode $contextFromTemplateRender,
    ) {}

    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Stmt\Class_) {
            return null;
        }

        if ($node->name === null) {
            return null;
        }

        $node->stmts = array_map(
            function ($node) {
                if ($node instanceof Node\Stmt\Property) {
                    if ($node->props[0]->name->name === 'macros') {
                        $node->setDocComment(new Doc(
                            <<<'DOC'
                                /**
                                 * @var array<string, \Twig\Template>
                                 */
                                DOC,
                        ));

                        return $node;
                    }
                }

                if ( ! $node instanceof Node\Stmt\ClassMethod) {
                    return $node;
                }

                if ($node->name->name === 'doDisplay') {
                    $node->setDocComment(
                        new Doc(
                            sprintf(
                                <<<'DOC'
                                    /**
                                     * @param %s $context
                                     * @param array{} $blocks
                                     * @return iterable<null|scalar|\Stringable>
                                     */
                                    DOC,
                                (new Printer())->print($this->contextFromTemplateRender),
                            ),
                        ),
                    );

                    $globals = new Node\Expr\MethodCall(
                        new Node\Expr\PropertyFetch(
                            new Node\Expr\Variable('this'),
                            'env',
                        ),
                        'getGlobals',
                    );

                    $node->stmts = [
                        // Add: $context += $this->env->getGlobals();
                        // PHPStan drops the unsealed part of a shape such as array{name?: string, ...<string, mixed>} on +,
                        // so a context without a shape gets the same result from: $context = \array_merge($this->env->getGlobals(), $context);
                        new Node\Stmt\Expression(
                            $this->contextFromTemplateRender->sealed
                                ? new Node\Expr\AssignOp\Plus(new Node\Expr\Variable('context'), $globals)
                                : new Node\Expr\Assign(
                                    new Node\Expr\Variable('context'),
                                    new Node\Expr\FuncCall(
                                        new Node\Name\FullyQualified('array_merge'),
                                        [new Node\Arg($globals), new Node\Arg(new Node\Expr\Variable('context'))],
                                    ),
                                ),
                        ),

                        ...$node->stmts ?? [],
                    ];

                    $node->stmts[] = new Node\Stmt\Expression(
                        new Node\Expr\YieldFrom(
                            new Node\Expr\Array_(),
                        ),
                    );

                    return $node;
                }

                // The context that a block is rendered with is merged in from where it is rendered, see TwigScopeInjector.
                // The block reads its variables from the context of the template, which keeps them known while the
                // context of the block is collected. Reading them from an empty context would make it never.
                if (str_starts_with($node->name->name, 'block_')) {
                    $node->setDocComment(new Doc(
                        sprintf(
                            <<<'DOC'
                                /**
                                 * @param %s $context
                                 * @param array{} $blocks
                                 * @return iterable<null|scalar|\Stringable>
                                 */
                                DOC,
                            (new Printer())->print($this->contextFromTemplateRender),
                        ),
                    ));

                    return $node;
                }

                return $node;
            },
            $node->stmts,
        );

        return $node;
    }
}

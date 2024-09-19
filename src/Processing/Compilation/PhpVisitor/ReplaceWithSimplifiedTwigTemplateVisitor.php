<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Builder\Class_;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use TwigStan\Processing\Compilation\TwigGlobalsToPhpDoc;
use TwigStan\Twig\SimplifiedTwigTemplate;

final class ReplaceWithSimplifiedTwigTemplateVisitor extends NodeVisitorAbstract
{
    public function __construct(
        private TwigGlobalsToPhpDoc $twigGlobalsToPhpDoc,
    ) {}

    public function leaveNode(Node $node): Node | null
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        if ($node->name === null) {
            return null;
        }

        $stmts = array_filter(
            $node->stmts,
            function ($node) {
                if ($node instanceof Node\Stmt\Property) {
                    return false;
                }

                if ($node instanceof Node\Stmt\ClassMethod) {
                    if (in_array($node->name->name, ['__construct', 'doGetParent', 'getTemplateName', 'isTraitable', 'getDebugInfo', 'getSourceContext'], true)) {
                        return false;
                    }
                }

                return true;
            },
        );

        $stmts = array_map(
            function ($node) {
                if (!$node instanceof Node\Stmt\ClassMethod) {
                    return $node;
                }

                if ($node->name->name === 'doDisplay') {
                    $node->setDocComment(new Doc(
                        sprintf(
                            <<<'DOC'
                            /**
                             * @param %s $__twigstan_globals
                             * @param array{} $__twigstan_context
                             * @return iterable<null|scalar|\Stringable>
                             */
                            DOC,
                            $this->twigGlobalsToPhpDoc->getGlobals(),
                        ),
                    ));
                    $node->name = new Node\Identifier('main');
                    $node->params = [
                        new Node\Param(
                            new Node\Expr\Variable('__twigstan_globals'),
                            null,
                            new Node\Identifier('array'),
                        ),
                        new Node\Param(
                            new Node\Expr\Variable('__twigstan_context'),
                            null,
                            new Node\Identifier('array'),
                        ),
                    ];
                    $node->returnType = new Node\Identifier('iterable');
                    $node->flags = ($node->flags & ~Node\Stmt\Class_::MODIFIER_PROTECTED) | Node\Stmt\Class_::MODIFIER_PUBLIC;
                    $node->stmts = array_filter(
                        $node->stmts ?? [],
                        function ($node) {
                            // Remove: $macros = $this->macros;
                            if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Assign && $node->expr->var instanceof Node\Expr\Variable && $node->expr->var->name === 'macros') {
                                return false;
                            }

                            return true;
                        },
                    );

                    $node->stmts = [
                        new Node\Stmt\Expression(
                            new Node\Expr\FuncCall(
                                new Node\Name('extract'),
                                [
                                    new Node\Arg(new Node\Expr\Variable('__twigstan_globals')),
                                ],
                            ),
                        ),
                        new Node\Stmt\Unset_(
                            [
                                new Node\Expr\Variable('__twigstan_globals'),
                            ],
                        ),
                        new Node\Stmt\Expression(
                            new Node\Expr\FuncCall(
                                new Node\Name('extract'),
                                [
                                    new Node\Arg(new Node\Expr\Variable('__twigstan_context')),
                                ],
                            ),
                        ),
                        new Node\Stmt\Unset_(
                            [
                                new Node\Expr\Variable('__twigstan_context'),
                            ],
                        ),
                        ...$node->stmts,
                        new Node\Stmt\Return_(
                            new Node\Expr\Array_(attributes: ['kind' => Node\Expr\Array_::KIND_SHORT]),
                        ),
                    ];
                    return $node;
                }

                if (str_starts_with($node->name->name, 'block_')) {
                    $node->setDocComment(new Doc(
                        <<<'DOC'
                        /**
                         * @param array{} $__twigstan_context
                         * @return iterable<null|scalar|\Stringable>
                         */
                        DOC,
                    ));
                    $node->params = [
                        new Node\Param(
                            new Node\Expr\Variable('__twigstan_context'),
                            null,
                            new Node\Identifier('array'),
                        ),
                    ];
                    $node->returnType = new Node\Identifier('iterable');
                    $node->flags = ($node->flags & ~Node\Stmt\Class_::MODIFIER_PROTECTED) | Node\Stmt\Class_::MODIFIER_PUBLIC;
                    $node->stmts = array_filter(
                        $node->stmts ?? [],
                        function ($node) {
                            // Remove: $macros = $this->macros;
                            if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Assign && $node->expr->var instanceof Node\Expr\Variable && $node->expr->var->name === 'macros') {
                                return false;
                            }

                            return true;
                        },
                    );
                    $node->stmts = [
                        // Add: extract($__twigstan_context);
                        new Node\Stmt\Expression(
                            new Node\Expr\FuncCall(
                                new Node\Name\FullyQualified('extract'),
                                [
                                    new Node\Arg(new Node\Expr\Variable('__twigstan_context')),
                                ],
                            ),
                        ),
                        // Unset: unset($__twigstan_context);
                        new Node\Stmt\Unset_(
                            [new Node\Expr\Variable('__twigstan_context')],
                        ),
                        ...$node->stmts,
                    ];
                    return $node;
                }

                return $node;
            },
            $stmts,
        );

        $builder = new Class_($node->name->name);
        $builder->extend(new Node\Name\FullyQualified(SimplifiedTwigTemplate::class));
        $builder->makeFinal();
        $builder->addStmts($stmts);

        return $builder->getNode();
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Builder\Class_;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Printer\Printer;
use TwigStan\Processing\Compilation\TwigGlobalsToPhpDoc;
use TwigStan\Twig\SimplifiedTwigTemplate;

final class ReplaceWithSimplifiedTwigTemplateVisitor extends NodeVisitorAbstract
{
    public function __construct(
        private TwigGlobalsToPhpDoc $twigGlobalsToPhpDoc,
    ) {}

    public function leaveNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Stmt\Class_) {
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
                if ( ! $node instanceof Node\Stmt\ClassMethod) {
                    return $node;
                }

                if ($node->name->name === 'doDisplay') {
                    $node->setDocComment(new Doc(
                        sprintf(
                            <<<'DOC'
                                /**
                                 * @param %s $context
                                 * @param array{} $blocks
                                 * @return iterable<null|scalar|\Stringable>
                                 */
                                DOC,
                            (new Printer())->print($this->twigGlobalsToPhpDoc->getGlobals()),
                        ),
                    ));
                    $node->name = new Node\Identifier('main');
                    $node->params = [
                        new Node\Param(
                            new Node\Expr\Variable('context'),
                            null,
                            new Node\Identifier('array'),
                        ),
                        new Node\Param(
                            new Node\Expr\Variable('blocks'),
                            null,
                            new Node\Identifier('array'),
                        ),
                    ];
                    $node->returnType = new Node\Identifier('iterable');
                    $node->flags = ($node->flags & ~Modifiers::PROTECTED) | Modifiers::PUBLIC;

                    return $node;
                }

                if (str_starts_with($node->name->name, 'block_')) {
                    $node->setDocComment(new Doc(
                        <<<'DOC'
                            /**
                             * @param array{} $context
                             * @param array{} $blocks
                             * @return iterable<null|scalar|\Stringable>
                             */
                            DOC,
                    ));
                    $node->params = [
                        new Node\Param(
                            new Node\Expr\Variable('context'),
                            null,
                            new Node\Identifier('array'),
                        ),
                        new Node\Param(
                            new Node\Expr\Variable('blocks'),
                            null,
                            new Node\Identifier('array'),
                        ),
                    ];
                    $node->returnType = new Node\Identifier('iterable');
                    $node->flags = ($node->flags & ~Modifiers::PROTECTED) | Modifiers::PUBLIC;

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

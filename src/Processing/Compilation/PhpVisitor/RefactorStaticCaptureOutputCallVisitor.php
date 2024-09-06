<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Twig\Extension\CoreExtension;

final class RefactorStaticCaptureOutputCallVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): Node|null
    {
        // Find: \Twig\Extension\CoreExtension::captureOutput((function () use(&$context, $macros, $blocks) { ... statements ... })())
        // Replace: \Twig\Extension\CoreExtension::captureOutput((function (array $__twigstan_context) {
        //  extract($__twigstan_context);
        //  unset($__twigstan_context);
        //  ... statements ...
        //})(get_defined_vars()))

        if (! $node instanceof Node\Expr\StaticCall) {
            return null;
        }

        if (! $node->class instanceof Node\Name) {
            return null;
        }

        if ($node->class->toString() !== CoreExtension::class) {
            return null;
        }

        if (! $node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name !== 'captureOutput') {
            return null;
        }


        if (count($node->args) !== 1) {
            return null;
        }

        $funcCall = $node->args[0]->value;

        if (! $funcCall instanceof Node\Expr\FuncCall) {
            return null;
        }

        $closure = $funcCall->name;

        if (! $closure instanceof Node\Expr\Closure) {
            return null;
        }

        $closure->params = [
            new Node\Param(
                new Node\Expr\Variable('__twigstan_context'),
                null,
                'array',
            ),
        ];
        $closure->uses = [];
        $closure->stmts = [
            new Node\Stmt\Expression(
                new Node\Expr\FuncCall(
                    new Node\Name('extract'),
                    [
                        new Node\Arg(
                            new Node\Expr\Variable('__twigstan_context'),
                        ),
                    ],
                ),
            ),
            new Node\Stmt\Unset_([new Node\Expr\Variable('__twigstan_context')]),

            ...$closure->stmts,
        ];

        $funcCall->args = [new Node\Arg(
            new Node\Expr\FuncCall(
                new Node\Name\FullyQualified('get_defined_vars'),
            ),
        )];

        return $node;

    }
}

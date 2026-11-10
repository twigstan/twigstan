<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;

#[Conditional(attribute: new AutowiredService(), if: 'not(%twigstan.phases.templateContextCollecting%)')]
final class PreserveContextVisitor extends NodeVisitorAbstract
{
    private bool $compiledTemplate = false;

    /**
     * @param array<Node> $nodes
     */
    public function beforeTraverse(array $nodes): null
    {
        $this->compiledTemplate = false;
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Class_ && str_starts_with($node->name?->toString() ?? '', '__TwigTemplate_')) {
                $this->compiledTemplate = true;

                break;
            }
        }

        return null;
    }

    /**
     * @return null|Node|list<Node>
     */
    public function leaveNode(Node $node): null | array | Node
    {
        if ( ! $this->compiledTemplate) {
            return null;
        }

        if (($node instanceof Node\Stmt\ClassMethod || $node instanceof Node\Expr\Closure) && $node->stmts !== null) {
            foreach ($node->params as $parameter) {
                if ($parameter->var instanceof Node\Expr\Variable
                    && $parameter->var->name === 'context'
                    && $parameter->type instanceof Node\Identifier
                    && $parameter->type->toLowerString() === 'array'
                ) {
                    array_unshift($node->stmts, $this->preserveContext());

                    break;
                }
            }

            return $node;
        }

        if ( ! $node instanceof Node\Stmt\Expression) {
            return null;
        }

        $assignment = $node->expr;

        if ( ! $assignment instanceof Node\Expr\Assign && ! $assignment instanceof Node\Expr\AssignRef && ! $assignment instanceof Node\Expr\AssignOp) {
            return null;
        }

        if ( ! $assignment->var instanceof Node\Expr\Variable || $assignment->var->name !== 'context') {
            return null;
        }

        return [$node, $this->preserveContext()];
    }

    private function preserveContext(): Node\Stmt\Expression
    {
        return new Node\Stmt\Expression(new Node\Expr\Assign(
            new Node\Expr\Variable('context'),
            new Node\Expr\FuncCall(
                new Node\Name\FullyQualified('twigstan_preserve_context'),
                [new Node\Arg(new Node\Expr\Variable('context'))],
            ),
        ));
    }
}

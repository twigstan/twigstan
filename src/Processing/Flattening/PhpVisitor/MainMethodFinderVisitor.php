<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class MainMethodFinderVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<Node\Stmt>
     */
    public array $stmts = [];

    /**
     * @var list<Node\Stmt\Property>
     */
    public array $properties = [];

    /**
     * @var list<Node\Stmt>
     */
    public array $constructorStmts = [];

    private bool $inTemplateClass = false;

    public function enterNode(Node $node): ?int
    {
        // Embedded templates, such as {% embed %}, are compiled to more classes after the class of the template.
        if ($node instanceof Node\Stmt\Class_) {
            if ($this->inTemplateClass) {
                return NodeVisitor::STOP_TRAVERSAL;
            }

            $this->inTemplateClass = true;

            return null;
        }

        if ($node instanceof Node\Stmt\Property) {
            $this->properties[] = $node;

            return null;
        }

        if ( ! $node instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        if ($node->stmts === null) {
            return null;
        }

        if ($node->name->name === '__construct') {
            $this->constructorStmts = array_values($node->stmts);

            return null;
        }

        if ($node->name->name !== 'doDisplay') {
            return null;
        }

        // Take the statements, but skip the first:
        // $context += $this->env->getGlobals();
        // $macros = $this->macros;
        $this->stmts = array_values(array_slice($node->stmts, 2));

        return null;
    }
}

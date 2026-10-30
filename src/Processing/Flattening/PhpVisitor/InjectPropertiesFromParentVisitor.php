<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Twig only declares some properties, such as $escaper, in templates that use them.
 * The inlined parent template can read properties that the child template does not declare.
 */
final class InjectPropertiesFromParentVisitor extends NodeVisitorAbstract
{
    /**
     * @param list<Node\Stmt\Property> $properties
     * @param list<Node\Stmt> $constructorStmts
     */
    public function __construct(
        private array $properties,
        private array $constructorStmts,
    ) {}

    public function enterNode(Node $node): ?int
    {
        if ( ! $node instanceof Node\Stmt\Class_) {
            return null;
        }

        $constructor = $node->getMethod('__construct');

        foreach ($this->properties as $property) {
            $name = $property->props[0]->name->name;

            if ($node->getProperty($name) !== null || ! $this->readsProperty($node, $name)) {
                continue;
            }

            array_unshift($node->stmts, $property);

            foreach ($this->constructorStmts as $stmt) {
                if ($constructor !== null && $this->assignsProperty($stmt, $name)) {
                    $constructor->stmts[] = $stmt;
                }
            }
        }

        // Embedded templates are compiled to more classes after the class of the template.
        return NodeVisitor::STOP_TRAVERSAL;
    }

    private function readsProperty(Node\Stmt\Class_ $class, string $name): bool
    {
        return (new NodeFinder())->findFirst(
            $class->stmts,
            fn(Node $node) => $node instanceof Node\Expr\PropertyFetch
                && $node->var instanceof Node\Expr\Variable
                && $node->var->name === 'this'
                && $node->name instanceof Node\Identifier
                && $node->name->name === $name,
        ) !== null;
    }

    private function assignsProperty(Node\Stmt $stmt, string $name): bool
    {
        return $stmt instanceof Node\Stmt\Expression
            && $stmt->expr instanceof Node\Expr\Assign
            && $stmt->expr->var instanceof Node\Expr\PropertyFetch
            && $stmt->expr->var->var instanceof Node\Expr\Variable
            && $stmt->expr->var->var->name === 'this'
            && $stmt->expr->var->name instanceof Node\Identifier
            && $stmt->expr->var->name->name === $name;
    }
}

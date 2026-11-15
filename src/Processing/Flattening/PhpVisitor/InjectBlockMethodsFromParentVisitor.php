<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use TwigStan\Twig\SourceLocation;

final class InjectBlockMethodsFromParentVisitor extends NodeVisitorAbstract
{
    /**
     * @param array<string, array{Node\Stmt\ClassMethod, SourceLocation}> $blocks
     */
    public function __construct(
        private array $blocks,
    ) {}

    private int $classes = 0;

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\Class_) {
            $this->classes++;
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node\Stmt\Class_
    {
        // Embedded templates are compiled to more classes after the class of the template, and they have blocks of their own.
        if ( ! $node instanceof Node\Stmt\Class_ || $this->classes !== 1) {
            return null;
        }

        // The blocks of the parent read their variables from the context of the parent, see AddTypeCommentsToTemplateVisitor.
        // In the child, they are rendered with the context of the child, which can be narrower than the context of the parent.
        $contextDoc = null;
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->name === 'doDisplay') {
                $contextDoc = $stmt->getDocComment();
            }
        }

        foreach ($this->blocks as $blockName => [$method,$sourceLocation]) {
            $method->name = new Node\Identifier($blockName);

            if ($contextDoc !== null) {
                $method->setDocComment($contextDoc);
            }

            if ($method->stmts === null) {
                continue;
            }

            $traverser = new NodeTraverser();
            $traverser->addVisitor(new AppendSourceLocationVisitor($sourceLocation));

            // @phpstan-ignore assign.propertyType (We pass in Stmt nodes and expect them to be returned)
            $method->stmts = $traverser->traverse($method->stmts);

            $node->stmts[] = $method;
        }

        return $node;
    }
}

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

    public function leaveNode(Node $node): ?Node\Stmt\Class_
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        foreach ($this->blocks as $blockName => [$method,$sourceLocation]) {
            $method->name = new Node\Identifier($blockName);

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

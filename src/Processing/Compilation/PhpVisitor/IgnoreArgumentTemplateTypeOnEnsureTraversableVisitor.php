<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Twig\Extension\CoreExtension;

final class IgnoreArgumentTemplateTypeOnEnsureTraversableVisitor extends NodeVisitorAbstract
{
    public function enterNode(Node $node): ?Node\Expr\StaticCall
    {
        if (!$node instanceof Node\Expr\StaticCall) {
            return null;
        }

        if (!$node->class instanceof Node\Name\FullyQualified) {
            return null;
        }

        if ($node->class->toString() !== CoreExtension::class) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name !== 'ensureTraversable') {
            return null;
        }

        $parent = $this->findStatementParent($node);

        if ($parent === null) {
            return null;
        }

        $parent->setAttribute('comments', [new Comment("// @phpstan-ignore argument.templateType")]);

        return $node;
    }

    private function findStatementParent(Node $node): ?Node\Stmt
    {
        $parent = $node->getAttribute('parent');

        if ($parent === null) {
            return null;
        }

        if ($parent instanceof Node\Stmt) {
            return $parent;
        }

        return $this->findStatementParent($parent);
    }
}

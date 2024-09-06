<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection\PhpVisitor;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use TwigStan\Twig\CommentHelper;
use TwigStan\Twig\SourceLocation;

final class PhpToTemplateLinesNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, SourceLocation>
     */
    private array $mapping = [];

    /**
     * @param Node[] $nodes
     *
     * @return Node[]
     */
    public function beforeTraverse(array $nodes): array
    {
        $this->mapping = [];

        return $nodes;
    }

    public function enterNode(Node $node): null
    {
        if (! $node instanceof Stmt) {
            return null;
        }

        if ($node->getComments() === []) {
            return null;
        }

        $sourceLocation = CommentHelper::getSourceLocationFromComments($node->getComments());

        if ($sourceLocation === null) {
            return null;
        }

        $this->mapping[$node->getLine()] = $sourceLocation;

        return null;
    }

    /**
     * @return array<int, SourceLocation>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }
}

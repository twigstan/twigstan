<?php

declare(strict_types=1);

namespace TwigStan\PHP;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

final class PhpToTemplateLinesNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var string
     * @see https://regex101.com/r/eQiVfK/1
     */
    private const string TWIG_LINE_REGEX = '#// line (?<line_number>\d+)+#';

    /**
     * @var array<int, int>
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

        foreach ($node->getComments() as $comment) {
            if (preg_match(self::TWIG_LINE_REGEX, $comment->getText(), $matches) !== 1) {
                continue;
            }


            $this->mapping[$node->getLine()] = (int) $matches['line_number'];
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }
}

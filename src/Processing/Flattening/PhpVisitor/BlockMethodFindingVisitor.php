<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class BlockMethodFindingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, Node\Stmt\ClassMethod>
     */
    public array $blocks = [];

    public function enterNode(Node $node): null
    {
        if ( ! $node instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        if ( ! str_starts_with($node->name->name, 'block_')) {
            return null;
        }

        $blockName = substr($node->name->name, 6);
        $this->blocks[$blockName] = $node;

        return null;
    }
}

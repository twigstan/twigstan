<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class MainMethodFinderVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<Node\Stmt>
     */
    public array $stmts = [];

    public function enterNode(Node $node): null
    {
        if (!$node instanceof Node\Stmt\ClassMethod) {
            return null;
        }

        if ($node->stmts === null) {
            return null;
        }

        if ($node->name->name !== 'main') {
            return null;
        }

        // Take the statements, but skip the first two:
        // extract($__twigstan_globals);
        // unset($__twigstan_context);
        $this->stmts = array_slice($node->stmts, 2);

        return null;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class BlockMethodFindingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, Node\Stmt\ClassMethod>
     */
    public array $blocks = [];

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

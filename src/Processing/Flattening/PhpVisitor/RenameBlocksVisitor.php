<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Renames the blocks that a template renders, such as 'header' in:
 *     yield from $this->unwrap()->yieldBlock('header', $context, $blocks);
 */
final class RenameBlocksVisitor extends NodeVisitorAbstract
{
    /**
     * @param array<string, string> $names The new name of each block
     */
    public function __construct(
        private readonly array $names,
    ) {}

    public function enterNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Expr\MethodCall || ! $node->name instanceof Node\Identifier) {
            return null;
        }

        if ( ! in_array($node->name->name, ['yieldBlock', 'renderBlock', 'displayBlock', 'hasBlock'], true)) {
            return null;
        }

        $name = $node->getArgs()[0]->value ?? null;

        if ( ! $name instanceof Node\Scalar\String_ || ! isset($this->names[$name->value])) {
            return null;
        }

        $name->value = $this->names[$name->value];

        return $node;
    }
}

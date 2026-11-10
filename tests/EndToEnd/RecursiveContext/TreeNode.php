<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RecursiveContext;

final readonly class TreeNode
{
    /**
     * @param list<TreeNode> $children
     */
    public function __construct(public array $children) {}
}

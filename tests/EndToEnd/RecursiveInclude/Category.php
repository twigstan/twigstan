<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RecursiveInclude;

final readonly class Category
{
    /**
     * @param list<Category> $children
     */
    public function __construct(
        public string $name,
        public array $children,
    ) {}
}

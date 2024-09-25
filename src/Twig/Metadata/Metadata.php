<?php

declare(strict_types=1);

namespace TwigStan\Twig\Metadata;

final readonly class Metadata
{
    /**
     * @param list<string> $parents
     * @param list<array{name: string, targets: array<string, string>}> $traits
     * @param list<string> $blocks
     * @param list<string> $parentBlocks
     */
    public function __construct(
        public string $name,
        public string $templateClassName,
        public string $filePath,
        public ?int $parentLineNumber,
        public array $parents,
        public array $traits,
        public array $blocks,
        public array $parentBlocks,
    ) {}

    public function hasParents(): bool
    {
        return $this->parents !== [];
    }

    public function hasResolvableParents(): bool
    {
        if ($this->parents === []) {
            return false;
        }

        return array_filter(
            $this->parents,
            fn($parent) => ! str_starts_with($parent, '$'),
        ) !== [];
    }

    public function hasTraits(): bool
    {
        return $this->traits !== [];
    }
}

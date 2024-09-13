<?php

declare(strict_types=1);

namespace TwigStan\Application;

use TwigStan\Twig\SourceLocation;

final readonly class TwigStanError
{
    /**
     * @param list<SourceLocation> $renderPoints
     */
    public function __construct(
        public string $message,
        public ?string $identifier,
        public ?string $tip,
        public string $phpFile,
        public int $phpLine,
        public ?SourceLocation $twigSourceLocation,
        public array $renderPoints,
    ) {}
}

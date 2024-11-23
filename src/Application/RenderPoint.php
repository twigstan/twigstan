<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Symfony\Component\Filesystem\Path;
use TwigStan\Twig\SourceLocation;

final readonly class RenderPoint
{
    public function __construct(
        public SourceLocation $sourceLocation,
        public string $context,
    ) {}

    public function toString(?string $relativeToDirectory = null): string
    {
        return sprintf(
            '%s:%d',
            $relativeToDirectory !== null ? Path::makeRelative($this->sourceLocation->fileName, $relativeToDirectory) : $this->sourceLocation->fileName,
            $this->sourceLocation->lineNumber,
        );
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Application;

use Symfony\Component\Filesystem\Path;

final readonly class RenderPoint
{
    public function __construct(
        public string $fileName,
        public int $lineNumber,
        public string $context,
    ) {}

    public function toString(?string $relativeToDirectory = null): string
    {
        return sprintf(
            '%s:%d',
            $relativeToDirectory !== null ? Path::makeRelative($this->fileName, $relativeToDirectory) : $this->fileName,
            $this->lineNumber,
        );
    }
}

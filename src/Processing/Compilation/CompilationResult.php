<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

final readonly class CompilationResult
{
    public function __construct(
        public string $twigFilePath,
        public string $phpFile,
    ) {}
}

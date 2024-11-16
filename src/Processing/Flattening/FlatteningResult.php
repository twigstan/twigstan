<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening;

final readonly class FlatteningResult
{
    public function __construct(
        public string $twigFilePath,
        public string $phpFile,
    ) {}
}

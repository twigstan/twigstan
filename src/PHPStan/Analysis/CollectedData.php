<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class CollectedData
{
    private function __construct(
        public mixed $data,
        public string $filePath,
        public string $collecterType,
    ) {}

    /**
     * @param array<mixed> $json
     */
    public static function decode(array $json): self
    {
        return new self(
            $json['data'],
            $json['filePath'],
            $json['collectorType'],
        );
    }
}

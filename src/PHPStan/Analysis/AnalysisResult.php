<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class AnalysisResult
{
    /**
     * @param list<Error> $errors
     * @param list<CollectedData> $collectedData
     */
    public function __construct(
        public array $errors,
        public array $collectedData,
    ) {}
}

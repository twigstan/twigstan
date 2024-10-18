<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class PHPStanAnalysisResult
{
    /**
     * @param list<Error> $errors
     * @param list<CollectedData> $collectedData
     * @param list<string> $notFileSpecificErrors
     */
    public function __construct(
        public int $exitCode,
        public array $errors,
        public array $collectedData,
        public array $notFileSpecificErrors,
    ) {}
}

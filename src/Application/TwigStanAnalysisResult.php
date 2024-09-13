<?php

declare(strict_types=1);

namespace TwigStan\Application;

final readonly class TwigStanAnalysisResult
{
    /**
     * @param list<TwigStanError> $errors
     * @param list<string> $fileSpecificErrors
     */
    public function __construct(
        public array $errors = [],
        public array $fileSpecificErrors = [],
    ) {}

    public function withFileSpecificError(string $error): self
    {
        return new self(
            $this->errors,
            [...$this->fileSpecificErrors, $error],
        );
    }

    public function withError(TwigStanError $error): self
    {
        return new self(
            [...$this->errors, $error],
            $this->fileSpecificErrors,
        );
    }

}

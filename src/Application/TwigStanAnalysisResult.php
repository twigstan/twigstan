<?php

declare(strict_types=1);

namespace TwigStan\Application;

final readonly class TwigStanAnalysisResult
{
    /**
     * @param list<TwigStanError> $errors
     * @param list<string> $fileSpecificErrors
     * @param array<positive-int, TwigStanRun> $runs
     */
    public function __construct(
        public array $errors = [],
        public array $fileSpecificErrors = [],
        public array $runs = [],
    ) {}

    public function withFileSpecificError(string $error): self
    {
        return new self(
            $this->errors,
            [...$this->fileSpecificErrors, $error],
            $this->runs,
        );
    }

    public function withError(TwigStanError $error): self
    {
        return new self(
            [...$this->errors, $error],
            $this->fileSpecificErrors,
            $this->runs,
        );
    }

    public function withRun(TwigStanRun $run): self
    {
        $runs = $this->runs;
        $runs[$run->number] = $run;

        return new self(
            $this->errors,
            $this->fileSpecificErrors,
            $runs,
        );
    }
}

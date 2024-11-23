<?php

declare(strict_types=1);

namespace TwigStan\Application;

use TwigStan\Processing\TemplateContext;

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
        public TemplateContext $context = new TemplateContext(),
    ) {}

    public function withFileSpecificError(string $error): self
    {
        return new self(
            $this->errors,
            [...$this->fileSpecificErrors, $error],
            $this->runs,
            $this->context,
        );
    }

    public function withError(TwigStanError $error): self
    {
        return new self(
            [...$this->errors, $error],
            $this->fileSpecificErrors,
            $this->runs,
            $this->context,
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
            $this->context,
        );
    }

    public function withContext(TemplateContext $templateContext): self
    {
        return new self(
            $this->errors,
            $this->fileSpecificErrors,
            $this->runs,
            $templateContext,
        );
    }
}

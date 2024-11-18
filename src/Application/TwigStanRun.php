<?php

declare(strict_types=1);

namespace TwigStan\Application;

use TwigStan\PHPStan\Analysis\Error;
use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;
use TwigStan\Processing\Compilation\CompilationResultCollection;
use TwigStan\Processing\Flattening\FlatteningResultCollection;
use TwigStan\Processing\ScopeInjection\ScopeInjectionResultCollection;
use TwigStan\Processing\TemplateContext;

final readonly class TwigStanRun
{
    /**
     * @param positive-int $number
     * @param list<Error> $errors
     * @param array<value-of<PHPStanRunMode>, PHPStanAnalysisResult> $phpstanAnalysisResults
     */
    public function __construct(
        public int $number,
        public TemplateContext $contextBefore,
        public TemplateContext $contextAfter,
        public array $errors,
        public CompilationResultCollection $compilationResults,
        public FlatteningResultCollection $flatteningResults,
        public ScopeInjectionResultCollection $scopeInjectionResults,
        public array $phpstanAnalysisResults,
    ) {}
}

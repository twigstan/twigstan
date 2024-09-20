<?php

namespace TwigStan\Baseline\Formatter;

use TwigStan\Application\TwigStanAnalysisResult;

interface BaselineErrorFormatter
{
    public function format(
        TwigStanAnalysisResult $analysisResult,
        string $existingBaselineContent,
    ): string;
}

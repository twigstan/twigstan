<?php

namespace TwigStan\Baseline\Formatter;

use TwigStan\Application\TwigStanAnalysisResult;

interface BaselineErrorFormattable
{
    public function format(
        TwigStanAnalysisResult $analysisResult,
        string $existingBaselineContent,
    ): string;
}

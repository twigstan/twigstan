<?php

declare(strict_types=1);

namespace TwigStan\PHPStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use TwigStan\PHPStan\Collector\ExportingCollector;

final readonly class AnalysisResultToJson implements ErrorFormatter
{
    public function __construct(
        private string $jsonFile,
    ) {}

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        file_put_contents(
            $this->jsonFile,
            json_encode([
                'fileSpecificErrors' => $analysisResult->getFileSpecificErrors(),
                'notFileSpecificErrors' => $analysisResult->getNotFileSpecificErrors(),
                'collectedData' => array_filter(
                    $analysisResult->getCollectedData(),
                    // @phpstan-ignore phpstanApi.runtimeReflection
                    fn($collectedData) => is_a($collectedData->getCollectorType(), ExportingCollector::class, true),
                ),
            ]),
        );

        return $analysisResult->hasErrors() ? 1 : 0;
    }
}

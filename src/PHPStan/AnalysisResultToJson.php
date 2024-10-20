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
        private bool $collectOnly,
        private bool $debugMode,
    ) {}

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        file_put_contents(
            $this->jsonFile,
            json_encode([
                'fileSpecificErrors' => $this->collectOnly === true ? [] : $analysisResult->getFileSpecificErrors(),
                'notFileSpecificErrors' => $analysisResult->getNotFileSpecificErrors(),
                'collectedData' => array_filter(
                    $analysisResult->getCollectedData(),
                    // @phpstan-ignore phpstanApi.runtimeReflection
                    fn($collectedData) => is_a($collectedData->getCollectorType(), ExportingCollector::class, true),
                ),
            ], $this->debugMode ? JSON_PRETTY_PRINT : 0),
        );

        return 0;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\PHPStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;
use TwigStan\Application\PHPStanRunMode;
use TwigStan\PHPStan\Collector\ExportingCollector;

final readonly class AnalysisResultToJson implements ErrorFormatter
{
    private PHPStanRunMode $mode;

    public function __construct(
        private string $jsonFile,
        string $mode,
        private bool $debugMode,
    ) {
        $this->mode = PHPStanRunMode::from($mode);
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        if ($output->isVerbose()) {
            $output->writeLineFormatted(sprintf(
                '<info>Writing analysis result to JSON file: "%s"</info>',
                $this->jsonFile,
            ));
        }

        file_put_contents(
            $this->jsonFile,
            json_encode([
                'fileSpecificErrors' => $this->mode === PHPStanRunMode::AnalyzeTwigTemplates ? $analysisResult->getFileSpecificErrors() : [],
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

<?php

declare(strict_types=1);

namespace TwigStan\PHPStan;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\ErrorFormatter\JsonErrorFormatter;
use PHPStan\Command\Output;

/**
 * This is abusing the ErrorFormatter to write the AnalysisResult to a JSON file.
 *
 * This formatter exists, because we want to run PHPStan with --debug / --xdebug / --verbose arguments.
 * As this corrupts the written JSON, we need to isolate the JSON from the generic PHPStan output.
 *
 * Next to that, we want to work with PHPStan's Error objects again.
 *
 * Also, in the future we want to retrieve the CollectedData as well.
 *
 * Ideally this becomes a core feature in PHPStan where the AnalysisResult can be written to a JSON file.
 *
 * First step for that to happen is to make the AnalysisResult implement JsonSerializable together with a `decode` method.
 */
final readonly class AnalysisResultToJson implements ErrorFormatter
{
    public function __construct(
        private JsonErrorFormatter $jsonErrorFormatter,
        private ?string $jsonFile = null,
    ) {}

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        if (!isset($this->jsonFile)) {
            // @phpstan-ignore phpstanApi.method (we don't care about this)
            return $this->jsonErrorFormatter->formatErrors($analysisResult, $output);
        }

        file_put_contents(
            $this->jsonFile,
            json_encode([
                'fileSpecificErrors' => $analysisResult->getFileSpecificErrors(),
                'notFileSpecificErrors' => $analysisResult->getNotFileSpecificErrors(),
                'internalErrors' => $analysisResult->getInternalErrorObjects(),
                'warnings' => $analysisResult->getWarnings(),
                'collectedData' => $analysisResult->getCollectedData(),
                'defaultLevelUsed' => $analysisResult->isDefaultLevelUsed(),
                'projectConfigFile' => $analysisResult->getProjectConfigFile(),
                'savedResultCache' => $analysisResult->isResultCacheSaved(),
                'peakMemoryUsageBytes' => $analysisResult->getPeakMemoryUsageBytes(),
                'isResultCacheUsed' => $analysisResult->isResultCacheUsed(),
                'changedProjectExtensionFilesOutsideOfAnalysedPaths' => $analysisResult->getChangedProjectExtensionFilesOutsideOfAnalysedPaths(),
            ]),
        );

        return $analysisResult->hasErrors() ? 1 : 0;
    }
}

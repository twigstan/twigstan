<?php

namespace TwigStan\Baseline\Formatter;

use Nette\DI\Helpers;
use Nette\Neon\Neon;
use Symfony\Component\Filesystem\Path;
use TwigStan\Application\TwigStanAnalysisResult;

final class BaselineNeonErrorFormatter implements BaselineErrorFormatter
{
    public function __construct(
        private string $currentWorkingDirectory,
    ) {}

    public function format(
        TwigStanAnalysisResult $analysisResult,
        string $existingBaselineContent,
    ): string {
        if (count($analysisResult->errors) === 0) {
            return $this->getNeon([], $existingBaselineContent);
        }

        $fileErrors = [];
        foreach ($analysisResult->errors as $fileSpecificError) {
            $twigFileName = $fileSpecificError->twigSourceLocation->fileName ?? $fileSpecificError->phpFile;
            if (!$twigFileName) {
                continue;
            }
            $fileErrors[Path::makeRelative($twigFileName, $this->currentWorkingDirectory)][] = $fileSpecificError->message;
        }
        ksort($fileErrors, SORT_STRING);

        $errorsToOutput = [];
        foreach ($fileErrors as $file => $errorMessages) {
            $fileErrorsCounts = [];
            foreach ($errorMessages as $errorMessage) {
                if (!isset($fileErrorsCounts[$errorMessage])) {
                    $fileErrorsCounts[$errorMessage] = 1;
                    continue;
                }

                $fileErrorsCounts[$errorMessage]++;
            }
            ksort($fileErrorsCounts, SORT_STRING);

            foreach ($fileErrorsCounts as $message => $count) {
                $errorsToOutput[] = [
                    'message' => Helpers::escape('#^' . preg_quote($message, '#') . '$#'),
                    'count' => $count,
                    'path' => Helpers::escape($file),
                ];
            }
        }

        return $this->getNeon($errorsToOutput, $existingBaselineContent);
    }

    /**
     * @param array<int, array{message: string, count: int, path: string}> $ignoreErrors
     */
    private function getNeon(array $ignoreErrors, string $existingBaselineContent): string
    {
        $neon = Neon::encode([
            'parameters' => [
                'ignoreErrors' => $ignoreErrors,
            ],
        ], true);

        if ($existingBaselineContent === '') {
            return substr($neon, 0, -1);
        }

        return substr($neon, 0, -2) . $existingBaselineContent;
    }
}

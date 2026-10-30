<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class AnalysisResultFromJsonReader
{
    public function __construct(
        private Filesystem $filesystem,
    ) {}

    public function read(string $file, int $exitCode): PHPStanAnalysisResult
    {
        if ( ! file_exists($file)) {
            return new PHPStanAnalysisResult(
                $exitCode,
                [],
                [],
                [
                    'Could not read results from PHPStan. This is most likely caused by a crash in PHPStan during analysis.',
                ],
            );
        }

        $content = $this->filesystem->readFile($file);

        if ( ! json_validate($content)) {
            throw new InvalidArgumentException(sprintf('File "%s" is not a valid JSON file.', $file));
        }

        $result = json_decode($content, true);

        $errors = array_values(array_map(
            Error::decode(...),
            $result['fileSpecificErrors'],
        ));

        $collectedData = [];
        foreach ($result['collectedData'] as $json) {
            // PHPStan groups the data that a collector collected in a file.
            foreach ($json['data'] as $data) {
                $collectedData[] = CollectedData::decode([...$json, 'data' => $data]);
            }
        }

        return new PHPStanAnalysisResult(
            $exitCode,
            $errors,
            $collectedData,
            $result['notFileSpecificErrors'],
        );
    }
}

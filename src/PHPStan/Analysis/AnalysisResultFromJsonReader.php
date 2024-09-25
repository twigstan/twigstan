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

    public function read(string $file): PHPStanAnalysisResult
    {
        if (!file_exists($file)) {
            return new PHPStanAnalysisResult(
                [],
                [],
                [
                    'Could not read results from PHPStan. This is most likely caused by a crash in PHPStan during analysis.',
                ],
            );
        }

        $content = $this->filesystem->readFile($file);

        if (!json_validate($content)) {
            throw new InvalidArgumentException(sprintf('File "%s" is not a valid JSON file', $file));
        }

        $result = json_decode($content, true);

        $errors = array_values(array_map(
            Error::decode(...),
            $result['fileSpecificErrors'],
        ));

        return new PHPStanAnalysisResult(
            $errors,
            array_values(array_map(
                CollectedData::decode(...),
                $result['collectedData'],
            )),
            $result['notFileSpecificErrors'],
        );
    }
}

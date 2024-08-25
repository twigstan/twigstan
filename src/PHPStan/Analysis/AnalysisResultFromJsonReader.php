<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

use InvalidArgumentException;
use TwigStan\Twig\Transforming\TransformResult;

final readonly class AnalysisResultFromJsonReader
{
    public function __construct(
        private ErrorFilter $errorFilter,
        private ErrorTransformer $errorTransformer,
        private ErrorToSourceFileMapper $errorToSourceFileMapper,
    ) {}

    /**
     * @param array<string, TransformResult> $mapping
     */
    public function read(string $file, array $mapping): AnalysisResult
    {
        if (!file_exists($file)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist', $file));
        }

        $result = json_decode(file_get_contents($file), true);

        return new AnalysisResult(
            $this->errorTransformer->transform(
                $this->errorFilter->filter(
                    $this->errorToSourceFileMapper->map(
                        $mapping,
                        array_map(
                            Error::decode(...),
                            $result['fileSpecificErrors'],
                        ),
                    ),
                ),
            ),
            array_map(
                CollectedData::decode(...),
                $result['collectedData'],
            ),
        );
    }
}

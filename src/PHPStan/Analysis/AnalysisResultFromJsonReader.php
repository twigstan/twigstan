<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use TwigStan\Processing\Flattening\FlatteningResultCollection;
use TwigStan\Processing\ScopeInjection\ScopeInjectionResultCollection;

final readonly class AnalysisResultFromJsonReader
{
    public function __construct(
        private ErrorFilter $errorFilter,
        private ErrorCollapser $errorCollapser,
        private ErrorTransformer $errorTransformer,
        private ErrorToSourceFileMapper $errorToSourceFileMapper,
        private Filesystem $filesystem,
    ) {}

    public function read(string $file, FlatteningResultCollection|ScopeInjectionResultCollection $mapping): AnalysisResult
    {
        if (!file_exists($file)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist', $file));
        }

        $content = $this->filesystem->readFile($file);

        if (!json_validate($content)) {
            throw new InvalidArgumentException(sprintf('File "%s" is not a valid JSON file', $file));
        }

        $result = json_decode($content, true);

        $errors = array_map(
            Error::decode(...),
            $result['fileSpecificErrors'],
        );

        if ($mapping instanceof ScopeInjectionResultCollection) {
            $errors = $this->errorToSourceFileMapper->map(
                $mapping,
                $errors,
            );
        }

        $errors = $this->errorFilter->filter($errors);
        $errors = $this->errorCollapser->collapse($errors);

        return new AnalysisResult(
            $this->errorTransformer->transform($errors),
            array_map(
                CollectedData::decode(...),
                $result['collectedData'],
            ),
            $result['notFileSpecificErrors'],
        );
    }
}

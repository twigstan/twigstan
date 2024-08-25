<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

use TwigStan\Twig\Transforming\TransformResult;

final readonly class ErrorToSourceFileMapper
{
    /**
     * @param array<string, TransformResult> $mapping
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function map(array $mapping, array $errors): array
    {
        return array_map(
            function (Error $error) use ($mapping) {
                if (!isset($mapping[$error->phpFile])) {
                    return $error;
                }

                $transformResult = $mapping[$error->phpFile];

                return $error->withTwigFileAndLineNumber(
                    $transformResult->twigFile,
                    $transformResult->getTwigLineNumber($error->phpLine),
                );
            },
            $errors,
        );
    }
}

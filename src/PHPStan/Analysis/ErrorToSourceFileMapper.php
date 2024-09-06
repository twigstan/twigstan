<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

use TwigStan\Processing\ScopeInjection\ScopeInjectionResultCollection;

final readonly class ErrorToSourceFileMapper
{
    /**
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function map(ScopeInjectionResultCollection $mapping, array $errors): array
    {
        return array_map(
            function (Error $error) use ($mapping) {
                if (!$mapping->hasPhpFile($error->phpFile)) {
                    return $error;
                }

                $transformResult = $mapping->getByPhpFile($error->phpFile);

                $sourceLocation = $transformResult->getSourceLocationForPhpLine($error->phpLine);

                if ($sourceLocation === null) {
                    return $error;
                }

                return $error->withTwigFileAndSourceLocation(
                    $transformResult->twigFileName,
                    $sourceLocation,
                );
            },
            $errors,
        );
    }
}

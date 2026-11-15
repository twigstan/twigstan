<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;
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
                if ($error->phpFile === null) {
                    return $error;
                }

                if ( ! $mapping->hasPhpFile($error->phpFile)) {
                    return $error;
                }

                $transformResult = $mapping->getByPhpFile($error->phpFile);

                // Errors in code that Twig generates before the first template line, such as properties, have no
                // source location, but they still belong to the template.
                return $error->withTwigFileAndSourceLocation(
                    $transformResult->twigFilePath,
                    $transformResult->getSourceLocationForPhpLine($error->phpLine),
                );
            },
            $errors,
        );
    }
}

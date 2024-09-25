<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;

final readonly class ErrorCollapser
{
    /**
     * Walks through all the errors and ignores errors with the same message and source location.
     * The duplicate error does not have to be next to each other.
     *
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function collapse(array $errors): array
    {
        $collapsed = [];
        foreach ($errors as $error) {
            if ($error->sourceLocation === null) {
                $collapsed[] = $error;
                continue;
            }

            $hash = hash(
                'crc32b',
                $error->message . $error->sourceLocation->fileName . $error->sourceLocation->lineNumber,
            );

            $collapsed[$hash] ??= $error;
        }

        return array_values($collapsed);
    }
}

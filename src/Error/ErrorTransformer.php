<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;

final readonly class ErrorTransformer
{
    /**
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function transform(array $errors): array
    {
        return array_map(
            function ($error) {
                if ($error->identifier === 'offsetAccess.notFound' && preg_match("/Offset '(?<variableName>.*)' (?<error>might not exist|does not exist) on (.*)\./", $error->message, $matches) === 1) {
                    return $error->withMessage(sprintf("Variable '%s' %s.", $matches['variableName'], $matches['error']));
                }

                return $error;
            },
            $errors,
        );
    }
}

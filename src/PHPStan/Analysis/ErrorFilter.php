<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class ErrorFilter
{
    private const array IDENTIFIERS_TO_IGNORE = [
        'isset.variable' => true,

        // When an array inside the context is not typed, this produces an error.
        'missingType.iterableValue' => '/__twigstan_context/',

        // When the variable that is passed does not exist, this produces an error.
        'argument.templateType' => '/CoreExtension::ensureTraversable/',
    ];

    /**
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function filter(array $errors): array
    {
        return array_filter(
            $errors,
            function ($error) {
                if ($error->identifier === null) {
                    return true;
                }

                $ignore = self::IDENTIFIERS_TO_IGNORE[$error->identifier] ?? null;

                if ($ignore === null) {
                    return true;
                }

                if ($ignore === true) {
                    return false;
                }

                return preg_match($ignore, $error->message) !== 1;
            },
        );
    }
}

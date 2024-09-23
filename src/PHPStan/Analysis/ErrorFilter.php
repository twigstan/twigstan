<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class ErrorFilter
{
    private const array IDENTIFIERS_TO_IGNORE = [
        'isset.variable',

        // It's perfectly fine to do `a == b ? 'yes' : 'no'` in Twig.
        'equal.notAllowed',

        // The context is backed up before a loop and restored after it.
        // Therefore this is a non-issue in Twig templates.
        'foreach.valueOverwrite',
    ];

    private const array IDENTIFIERS_ERROR_PATTERNS_TO_IGNORE = [
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

                if (in_array($error->identifier, self::IDENTIFIERS_TO_IGNORE, true)) {
                    return false;
                }

                $pattern = self::IDENTIFIERS_ERROR_PATTERNS_TO_IGNORE[$error->identifier] ?? null;

                if ($pattern === null) {
                    return true;
                }

                return preg_match($pattern, $error->message) !== 1;
            },
        );
    }
}

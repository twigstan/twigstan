<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class ErrorFilter
{
    private const array IDENTIFIERS_TO_IGNORE = [
        'nullsafe.neverNull',
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

                return !in_array(
                    $error->identifier,
                    self::IDENTIFIERS_TO_IGNORE,
                    true,
                );
            },
        );
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

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
                if (!str_contains($error->message, '$')) {
                    return $error;
                }

                return $error->withMessage(str_replace('$', '', $error->message));
            },
            $errors,
        );
    }
}

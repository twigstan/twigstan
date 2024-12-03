<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;

final readonly class ErrorFilter
{
    /**
     * @param list<Ignorable> $ignoreErrors
     */
    public function __construct(private array $ignoreErrors) {}

    /**
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function filter(array $errors): array
    {
        return array_values(array_filter(
            $errors,
            function ($error) {
                foreach ($this->ignoreErrors as $ignoreError) {
                    if ($ignoreError->shouldIgnore($error)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }
}

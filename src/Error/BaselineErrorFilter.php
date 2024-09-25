<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;

final readonly class BaselineErrorFilter
{
    /**
     * @param list<BaselineError> $baselineErrors
     */
    public function __construct(
        private array $baselineErrors,
        private bool $reportUnmatchedIgnoredErrors,
    ) {}


    /**
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function filter(array $errors): array
    {
        $errors = array_values(array_filter(
            $errors,
            function ($error) {
                foreach ($this->baselineErrors as $baselineError) {
                    if ($baselineError->shouldIgnore($error)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        foreach ($this->baselineErrors as $baselineError) {
            if ($baselineError->hits === $baselineError->count) {
                continue;
            }

            if (!$this->reportUnmatchedIgnoredErrors && $baselineError->count > $baselineError->hits) {
                continue;
            }

            $errors[] = new Error(
                sprintf(
                    "Baseline error is expected to occur %d %s, but occurred only %d %s.\n%s",
                    $baselineError->count,
                    $baselineError->count === 1 ? 'time' : 'times',
                    $baselineError->hits,
                    $baselineError->hits === 1 ? 'time' : 'times',
                    $baselineError,
                ),
                null,
                0,
                null,
                null,
                'ignore.count',
                null,
                false,
            );
        }

        return $errors;
    }
}

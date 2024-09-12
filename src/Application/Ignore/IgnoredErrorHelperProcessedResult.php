<?php

declare(strict_types=1);

namespace TwigStan\Application\Ignore;

use TwigStan\Application\TwigStanError;

final class IgnoredErrorHelperProcessedResult
{
    /**
     * @param list<TwigStanError> $notIgnoredErrors
     * @param list<array{TwigStanError, mixed[]|string}> $ignoredErrors
     */
    public function __construct(
        private array $notIgnoredErrors,
        private array $ignoredErrors,
    ) {}

    /**
     * @return list<TwigStanError>
     */
    public function getNotIgnoredErrors(): array
    {
        return $this->notIgnoredErrors;
    }

    /**
     * @return list<array{TwigStanError, mixed[]|string}>
     */
    public function getIgnoredErrors(): array
    {
        return $this->ignoredErrors;
    }
}

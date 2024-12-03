<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;

final readonly class IgnoreByIdentifierWhenSourceLocationHasPrevious implements Ignorable
{
    /**
     * @param list<string> $identifiers
     */
    public function __construct(private array $identifiers) {}

    public function shouldIgnore(Error $error): bool
    {
        if ($error->sourceLocation?->previous === null) {
            return false;
        }

        return in_array($error->identifier, $this->identifiers, true);
    }
}

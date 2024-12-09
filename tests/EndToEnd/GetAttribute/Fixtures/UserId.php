<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute\Fixtures;

use Stringable;

final readonly class UserId implements Stringable
{
    public function __construct(public string $id) {}

    public function __toString(): string
    {
        return $this->id;
    }
}

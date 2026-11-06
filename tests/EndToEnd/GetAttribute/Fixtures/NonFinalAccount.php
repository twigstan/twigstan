<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute\Fixtures;

class NonFinalAccount
{
    public function __construct(
        public string $name,
    ) {}
}

<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute\Fixtures;

class MagicMethods
{
    /**
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): string
    {
        return sprintf('%s from __call()', $name);
    }
}

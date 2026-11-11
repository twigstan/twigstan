<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute\Fixtures;

class MagicProperties
{
    public function __isset(string $name): bool
    {
        return true;
    }

    public function __get(string $name): string
    {
        return sprintf('%s from __get()', $name);
    }
}

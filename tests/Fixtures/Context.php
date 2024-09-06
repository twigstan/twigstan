<?php

declare(strict_types=1);

namespace TwigStan\Fixtures;

use ArrayAccess;

/**
 * @implements ArrayAccess<string, string|null>
 */
final class Context implements ArrayAccess
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}

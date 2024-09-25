<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;

final class IgnoreError
{
    public function __construct(
        public ?string $message = null,
        public ?string $identifier = null,
        public ?string $path = null,
        public int $hits = 0,
    ) {}

    public function shouldIgnore(Error $error): bool
    {
        if ($this->identifier !== null && $this->identifier !== $error->identifier) {
            return false;
        }

        if ($this->message !== null && $this->message !== $error->message && preg_match($this->message, $error->message) !== 1) {
            return false;
        }

        if ($this->path !== null && ($error->sourceLocation === null || ! fnmatch($this->path, $error->sourceLocation->fileName, FNM_NOESCAPE))) {
            return false;
        }

        $this->hits++;

        return true;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Error;

use Stringable;
use TwigStan\PHPStan\Analysis\Error;

final class BaselineError implements Stringable
{
    /**
     * @param string $path Absolute path to Twig file
     */
    public function __construct(
        public readonly string $message,
        public readonly ?string $identifier,
        public readonly string $path,
        public int $count = 1,
        public int $hits = 0,
    ) {}

    public function increaseCount(): void
    {
        $this->count++;
    }

    public function shouldIgnore(Error $error): bool
    {
        if ($error->sourceLocation === null) {
            return false;
        }

        if ($this->identifier !== null && $this->identifier !== $error->identifier) {
            return false;
        }

        if ($this->message !== $error->message) {
            return false;
        }

        if ($this->path !== $error->twigFile) {
            return false;
        }

        $this->hits++;

        if ($this->hits > $this->count) {
            return false;
        }

        return true;
    }

    public function __toString(): string
    {
        $message = $this->message;

        if ($this->identifier !== null) {
            $message = sprintf('%s (%s)', $message, $this->identifier);
        }

        return sprintf('%s in path %s', $message, $this->path);
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Error;

use InvalidArgumentException;
use TwigStan\PHPStan\Analysis\Error;

final class IgnoreError
{
    /**
     * @param null|string $path Path to Twig file or directory. This can contain wildcards.
     */
    private function __construct(
        public readonly ?string $message = null,
        public readonly ?string $identifier = null,
        public readonly ?string $path = null,
        private int $hits = 0,
    ) {
        if ($message !== null && @preg_match($message, '') === false) {
            throw new InvalidArgumentException(sprintf('Invalid pattern "%s".', $message));
        }
    }

    public static function create(string $message, string $identifier, string $path): self
    {
        return new self($message, $identifier, $path);
    }

    public static function message(string $message): self
    {
        return new self(message: $message);
    }

    public static function identifier(string $identifier): self
    {
        return new self(identifier: $identifier);
    }

    /**
     * @param string $path Path to Twig file or directory. This can contain wildcards.
     */
    public static function path(string $path): self
    {
        return new self(path: $path);
    }

    public static function messageAndIdentifier(string $message, string $identifier): self
    {
        return new self(message: $message, identifier: $identifier);
    }

    /**
     * @param string $path Path to Twig file or directory. This can contain wildcards.
     */
    public static function messageAndPath(string $message, string $path): self
    {
        return new self(message: $message, path: $path);
    }

    /**
     * @param string $path Path to Twig file or directory. This can contain wildcards.
     */
    public static function identifierAndPath(string $identifier, string $path): self
    {
        return new self(identifier: $identifier, path: $path);
    }

    public function shouldIgnore(Error $error): bool
    {
        if ($this->identifier !== null && $this->identifier !== $error->identifier) {
            return false;
        }

        if ($this->message !== null && $this->message !== $error->message && preg_match($this->message, $error->message) !== 1) {
            return false;
        }

        if ($this->path !== null && ($error->twigFile === null || ! fnmatch($this->path, $error->twigFile, FNM_NOESCAPE))) {
            return false;
        }

        $this->hits++;

        return true;
    }
}

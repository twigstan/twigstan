<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

use TwigStan\Twig\SourceLocation;

final readonly class Error
{
    public function __construct(
        public string $message,
        public ?string $phpFile = null,
        public int $phpLine = 0,
        public ?string $twigFile = null,
        public ?SourceLocation $sourceLocation = null,
        public ?string $identifier = null,
        public ?string $tip = null,
        public bool $canBeIgnored = true,
    ) {}

    /**
     * @param array<mixed> $json
     */
    public static function decode(array $json): self
    {
        return new self(
            $json['message'],
            $json['file'],
            $json['line'] ?? 0,
            null,
            null,
            $json['identifier'],
            $json['tip'],
            $json['canBeIgnored'],
        );
    }

    public function withMessage(string $message): self
    {
        return new self(
            $message,
            $this->phpFile,
            $this->phpLine,
            $this->twigFile,
            $this->sourceLocation,
            $this->identifier,
            $this->tip,
        );
    }

    public function withTwigFileAndSourceLocation(string $twigFile, SourceLocation $sourceLocation): self
    {
        return new self(
            $this->message,
            $this->phpFile,
            $this->phpLine,
            $twigFile,
            $sourceLocation,
            $this->identifier,
            $this->tip,
        );
    }
}

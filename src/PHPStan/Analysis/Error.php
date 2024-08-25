<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Analysis;

final readonly class Error
{
    private function __construct(
        public string $message,
        public string $phpFile,
        public int $phpLine,
        public ?string $twigFile,
        public int $twigLine,
        public ?string $identifier,
        public ?string $tip,
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
            0,
            $json['identifier'],
            $json['tip'],
        );
    }

    public function withMessage(string $message): self
    {
        return new self(
            $message,
            $this->phpFile,
            $this->phpLine,
            $this->twigFile,
            $this->twigLine,
            $this->identifier,
            $this->tip,
        );
    }

    public function withTwigFileAndLineNumber(string $file, int $line): self
    {
        return new self(
            $this->message,
            $this->phpFile,
            $this->phpLine,
            $file,
            $line,
            $this->identifier,
            $this->tip,
        );
    }
}

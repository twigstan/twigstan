<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming;

final readonly class TransformResult
{
    /**
     * @param array<int, int> $phpToTwigLineMapping
     */
    public function __construct(
        public string $twigFile,
        public string $phpFile,
        public array $phpToTwigLineMapping,
    ) {}

    public function getTwigLineNumber(int $line): int
    {
        do {
            if (isset($this->phpToTwigLineMapping[$line])) {
                return $this->phpToTwigLineMapping[$line];
            }

            $line--;
        } while ($line > 0);
    }
}

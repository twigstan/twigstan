<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection;

use TwigStan\Twig\SourceLocation;

final readonly class ScopeInjectionResult
{
    /**
     * @param array<int, SourceLocation> $phpToTwigLineMapping
     */
    public function __construct(
        public string $twigFilePath,
        public string $phpFile,
        public array $phpToTwigLineMapping,
    ) {}

    public function getSourceLocationForPhpLine(int $line): ?SourceLocation
    {
        do {
            if (isset($this->phpToTwigLineMapping[$line])) {
                return $this->phpToTwigLineMapping[$line];
            }

            $line--;
        } while ($line > 0);

        return null;
    }
}

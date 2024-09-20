<?php

namespace TwigStan\Configuration;

class PathProvider
{
    /**
     * @param list<string> $phpPaths
     * @param list<string> $twigPaths
     */
    public function __construct(
        private array $phpPaths = [],
        private array $twigPaths = [],
    ) {}

    /**
     * @return list<string>
     */
    public function getPhpPaths(): array
    {
        return $this->phpPaths;
    }

    /**
     * @return list<string>
     */
    public function getTwigPaths(): array
    {
        return $this->twigPaths;
    }
}

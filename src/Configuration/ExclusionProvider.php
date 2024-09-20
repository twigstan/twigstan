<?php

namespace TwigStan\Configuration;

class ExclusionProvider
{
    /**
     * @param list<string> $phpExcludes
     * @param list<string> $twigExcludes
    */
    public function __construct(
        private array $phpExcludes,
        private array $twigExcludes,
    ) {}

    /**
     * @return list<string>
     */
    public function getPhpExcludes(): array
    {
        return $this->phpExcludes;
    }

    /**
     * @return list<string>
     */
    public function getTwigExcludes(): array
    {
        return $this->twigExcludes;
    }
}

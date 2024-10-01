<?php

declare(strict_types=1);

namespace TwigStan\Finder;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class FilesFinder
{
    /**
     * @param list<string> $paths
     * @param list<string> $exclusions
     */
    public function __construct(
        private string $namePattern,
        private array $paths,
        private array $exclusions,
    ) {}

    /**
     * @return array<string, SplFileInfo>
     */
    public function find(): array
    {
        if ($this->paths === []) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->name([$this->namePattern])
            ->in($this->paths)
            ->sortByName()
            ->filter(function (SplFileInfo $file) {
                foreach ($this->exclusions as $exclude) {
                    if (fnmatch($exclude, $file->getRealPath(), FNM_NOESCAPE)) {
                        return false;
                    }
                }

                return true;
            });

        return iterator_to_array($finder);
    }
}

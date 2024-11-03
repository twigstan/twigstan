<?php

declare(strict_types=1);

namespace TwigStan\Finder;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class FilesFinder
{
    /**
     * @param list<string> $extensions
     * @param list<string> $paths
     * @param list<string> $exclusions
     */
    public function __construct(
        private array $extensions,
        private array $paths,
        private array $exclusions,
        private string $currentWorkingDirectory,
    ) {}

    /**
     * @return array<string, SplFileInfo>
     */
    public function find(): array
    {
        if ($this->paths === []) {
            return [];
        }

        $directories = [];
        $files = [];
        foreach ($this->paths as $path) {
            if (is_dir($path)) {
                $directories[] = $path;

                continue;
            }

            if (is_file($path)) {
                $files[] = new SplFileInfo(
                    $path,
                    basename(Path::makeRelative($path, $this->currentWorkingDirectory)),
                    Path::makeRelative($path, $this->currentWorkingDirectory),
                );

                continue;
            }

            throw new InvalidArgumentException(sprintf('Path %s is not a file or directory.', $path));
        }

        if ($files === [] && $directories === []) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->name(array_map(
                fn($extension) => sprintf('*.%s', $extension),
                $this->extensions,
            ))
            ->in($directories)
            ->append($files)
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

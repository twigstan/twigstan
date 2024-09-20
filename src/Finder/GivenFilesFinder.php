<?php

namespace TwigStan\Finder;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class GivenFilesFinder
{
    /**
     * @param list{php?: string[], twig?: string[]} $exclusions
     */
    public function __construct(
        private array $exclusions,
        private string $currentWorkingDirectory,
    ) {}

    /**
     * @param list<string> $paths
     * @return array<string, SplFileInfo>
     */
    public function find(array $paths): array
    {
        $paths = array_map(
            fn($path) => Path::makeAbsolute($path, $this->currentWorkingDirectory),
            $paths,
        );

        $directories = [];
        $files = [];
        foreach ($paths as $path) {
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

            throw new InvalidArgumentException(sprintf('Path %s is not a file or directory', $path));
        }

        if ($files === [] && $directories === []) {
            return [];
        }

        $exclusions = array_merge(
            $this->exclusions['php'] ?? [],
            $this->exclusions['twig'] ?? [],
        );

        $finder = Finder::create()
            ->files()
            ->name(['*.twig', '*.php'])
            ->notName('*.untrack.php') // @todo remove later
            ->in($directories)
            ->append($files)
            ->filter(function (SplFileInfo $file) use ($exclusions) {
                foreach ($exclusions as $exclude) {
                    if (fnmatch($exclude, $file->getRealPath(), FNM_NOESCAPE)) {
                        return false;
                    }
                }

                return true;
            });

        return iterator_to_array($finder);
    }
}

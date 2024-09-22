<?php

namespace TwigStan\Finder;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class GivenFilesFinder
{
    public function __construct(
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

        $finder = Finder::create()
            ->files()
            ->name(['*.twig', '*.php'])
            ->in($directories)
            ->append($files)
            ->sortByName();

        return iterator_to_array($finder);
    }
}

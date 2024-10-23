<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use TwigStan\Twig\Metadata\MetadataRegistry;

final readonly class DependencySorter
{
    public function __construct(
        private MetadataRegistry $metadataRegistry,
    ) {}

    /**
     * Returns a list of twig file names sorted by dependencies. The first element of the list has no dependencies.
     *
     * @param list<string> $twigFileNames
     *
     * @return list<string>
     */
    public function sortByDependencies(array $twigFileNames): array
    {
        $dependants = [];

        foreach ($twigFileNames as $twigFileName) {
            $metadata = $this->metadataRegistry->getMetadata($twigFileName);
            $dependants[$twigFileName] = array_filter(
                $metadata->parents,
                fn($parent) => ! str_starts_with($parent, '$'),
            );
        }

        $sorted = [];
        $visited = [];

        $visit = function ($file) use (&$visit, &$sorted, &$visited, $dependants): void {
            if ( ! isset($visited[$file])) {
                $visited[$file] = true;
                foreach ($dependants[$file] as $dependency) {
                    $visit($dependency);
                }

                $sorted[] = $file;
            }
        };

        foreach (array_keys($dependants) as $file) {
            $visit($file);
        }

        return $sorted;
    }
}

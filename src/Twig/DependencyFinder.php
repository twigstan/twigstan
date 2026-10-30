<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use TwigStan\Twig\Metadata\MetadataRegistry;

final readonly class DependencyFinder
{
    public function __construct(
        private MetadataRegistry $metadataRegistry,
    ) {}

    /**
     * @param list<string> $twigFileNames
     * @return list<string>
     */
    public function getDependencies(array $twigFileNames): array
    {
        $dependencies = [];
        foreach ($twigFileNames as $twigFileName) {
            array_push(
                $dependencies,
                ...$this->findDependantsForFile($twigFileName),
            );
        }

        return $dependencies;
    }

    /**
     * @param array<string, true> $visited Templates can depend on each other, or on themselves.
     *
     * @return list<string>
     */
    private function findDependantsForFile(string $twigFileName, array &$visited = []): array
    {
        $visited[$twigFileName] = true;

        $dependants = [];

        $metadata = $this->metadataRegistry->getMetadata($twigFileName);

        $dependencies = $metadata->dependencies;

        if ($metadata->hasResolvableParents()) {
            $dependencies = [...$metadata->parents, ...$dependencies];
        }

        foreach ($dependencies as $dependency) {
            if (isset($visited[$dependency])) {
                continue;
            }

            array_push(
                $dependants,
                $dependency,
                ...$this->findDependantsForFile($dependency, $visited),
            );
        }

        return $dependants;
    }
}

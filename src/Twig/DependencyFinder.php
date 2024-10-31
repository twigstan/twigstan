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
     * @return list<string>
     */
    private function findDependantsForFile(string $twigFileName): array
    {
        $dependants = [];

        $metadata = $this->metadataRegistry->getMetadata($twigFileName);

        if ($metadata->hasResolvableParents()) {
            foreach ($metadata->parents as $parentTwigFileName) {
                if (in_array($parentTwigFileName, $dependants, true)) {
                    continue;
                }

                array_push(
                    $dependants,
                    $parentTwigFileName,
                    ...$this->findDependantsForFile($parentTwigFileName),
                );
            }
        }

        foreach ($metadata->macros as $macro) {
            if (in_array($macro, $dependants, true)) {
                continue;
            }

            array_push(
                $dependants,
                $macro,
                ...$this->findDependantsForFile($macro),
            );
        }

        return $dependants;
    }
}

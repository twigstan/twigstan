<?php

declare(strict_types=1);

namespace TwigStan\Twig\Metadata;

final class MetadataRegistry
{
    /**
     * @var array<string, Metadata>
     */
    private array $metadata;

    public function __construct(
        private MetadataAnalyzer $metadataAnalyzer,
    ) {}

    public function getMetadata(string $name): Metadata
    {
        $this->metadata[$name] ??= $this->metadataAnalyzer->getMetadata($name);

        return $this->metadata[$name];
    }

    //public function findByTwigFile(string $filePath): Metadata
    //{
    //    $this->metadata ??= $this->metadataAnalyzer->getMetadata();
    //
    //    foreach ($this->metadata as $metadata) {
    //        if ($metadata->filePath === $filePath) {
    //            return $metadata;
    //        }
    //    }
    //
    //    throw new \RuntimeException(sprintf('Metadata not found for file path %s', $filePath));
    //}
}

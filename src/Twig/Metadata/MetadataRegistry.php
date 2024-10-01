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
}

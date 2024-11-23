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

    /**
     * @return list<string>
     */
    public function getAbstractTemplates(): array
    {
        return array_values(
            array_map(
                fn(Metadata $metadata) => $metadata->filePath,
                array_filter($this->metadata, fn(Metadata $metadata) => $metadata->isAbstract),
            ),
        );
    }
}

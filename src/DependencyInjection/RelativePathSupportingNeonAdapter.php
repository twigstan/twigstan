<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\DI\Config\Adapter;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\Schema\Processor;

final readonly class RelativePathSupportingNeonAdapter implements Adapter
{
    public function __construct(
        private NeonAdapter $neonAdapter,
        private SchemaFactory $schemaFactory,
        private Processor $processor,
    ) {}

    /**
     * @return array<mixed>
     */
    public function load(string $file): array
    {
        return $this->processor->process(
            $this->schemaFactory->create($file),
            $this->neonAdapter->load($file),
        );
    }
}

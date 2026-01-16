<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;
use TwigStan\PHPStan\Collector\TemplateContextCollector;
use TwigStan\Twig\SourceLocation;
use TwigStan\Twig\TwigFileCanonicalizer;

final readonly class TemplateContextFactory
{
    public function __construct(
        private TwigFileCanonicalizer $twigFileCanonicalizer,
    ) {}

    public function create(PHPStanAnalysisResult $analysisResult): TemplateContext
    {
        /**
         * @var array<string, array<string, array{SourceLocation, string}>> $templateContext
         */
        $templateContext = [];
        foreach ($analysisResult->collectedData as $data) {
            if (is_a($data->collecterType, TemplateContextCollector::class, true)) {
                // PHPStan aggregates collector results per file
                // In PHPStan 2.1+, data is list<list<TemplateData>>
                // In earlier versions, data might be list<TemplateData>
                foreach ($data->data as $nodeResults) {
                    // Check if this is a TemplateData directly or a list of TemplateData
                    if (isset($nodeResults['template'])) {
                        // Single-nested: $nodeResults is TemplateData
                        $renderDataList = [$nodeResults];
                    } else {
                        // Double-nested: $nodeResults is list<TemplateData>
                        $renderDataList = $nodeResults;
                    }

                    foreach ($renderDataList as $renderData) {
                        if ( ! is_array($renderData) || ! isset($renderData['template'])) {
                            continue;
                        }

                        $template = $this->twigFileCanonicalizer->absolute($renderData['template']);
                        $sourceLocation = SourceLocation::decode($renderData['sourceLocation']);

                        $templateContext[$template][$sourceLocation->getHash()] = [$sourceLocation, $renderData['context']];
                    }
                }
            }
        }

        return new TemplateContext($templateContext);
    }
}

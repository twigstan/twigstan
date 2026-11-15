<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use TwigStan\PHPStan\Analysis\PHPStanAnalysisResult;
use TwigStan\PHPStan\Collector\ContextFromTwigComponentClassCollector;
use TwigStan\PHPStan\Collector\ContextFromTwigComponentTagCollector;
use TwigStan\PHPStan\Collector\TemplateContextCollector;
use TwigStan\Twig\SourceLocation;
use TwigStan\Twig\TwigFileCanonicalizer;

final readonly class TemplateContextFactory
{
    public function __construct(
        private TwigFileCanonicalizer $twigFileCanonicalizer,
    ) {}

    /**
     * @param list<string> $twigComponentClassTemplates Templates of class components, see getTwigComponentClassTemplates().
     */
    public function create(PHPStanAnalysisResult $analysisResult, array $twigComponentClassTemplates = []): TemplateContext
    {
        /**
         * @var array<string, array<string, array{SourceLocation, string}>> $templateContext
         */
        $templateContext = [];
        foreach ($analysisResult->collectedData as $data) {
            if (is_a($data->collecterType, TemplateContextCollector::class, true)) {
                foreach ($data->data as $renderData) {
                    $template = $this->twigFileCanonicalizer->absolute($renderData['template']);

                    // A class component gets its context from the class, not from the tags that render it.
                    if ($data->collecterType === ContextFromTwigComponentTagCollector::class && in_array($template, $twigComponentClassTemplates, true)) {
                        continue;
                    }

                    if (isset($renderData['macro'])) {
                        $template = TemplateContext::macroKey($template, $renderData['macro']);
                    }

                    $sourceLocation = SourceLocation::decode($renderData['sourceLocation']);

                    $templateContext[$template][$sourceLocation->getHash()] = [$sourceLocation, $renderData['context']];
                }
            }
        }

        return new TemplateContext($templateContext);
    }

    /**
     * @return list<string>
     */
    public function getTwigComponentClassTemplates(PHPStanAnalysisResult $analysisResult): array
    {
        $templates = [];
        foreach ($analysisResult->collectedData as $data) {
            if ($data->collecterType !== ContextFromTwigComponentClassCollector::class) {
                continue;
            }

            foreach ($data->data as $renderData) {
                $templates[] = $this->twigFileCanonicalizer->absolute($renderData['template']);
            }
        }

        return array_values(array_unique($templates));
    }
}

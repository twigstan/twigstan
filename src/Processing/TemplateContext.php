<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use TwigStan\Twig\SourceLocation;

final readonly class TemplateContext
{
    /**
     * @var array<string, array<string, array{SourceLocation, string}>>
     */
    public array $context;

    /**
     * @param array<string, array<string, array{SourceLocation, string}>> $context
     */
    public function __construct(
        array $context = [],
    ) {
        $this->context = array_map(
            function ($renderPoints) {
                uasort($renderPoints, fn($left, $right) => $left[0]->sort($right[0]));

                return $renderPoints;
            },
            $context,
        );
    }

    /**
     * @return list<string>
     */
    public function getUniqueContextByTemplate(string $template): array
    {
        $uniqueContext = [];
        foreach ($this->context[$template] ?? [] as [$sourceLocation, $context]) {
            $uniqueContext[] = $context;
        }

        return array_values(array_unique($uniqueContext));
    }

    /**
     * @return array<string, array{SourceLocation, string}>
     */
    public function getByTemplate(string $template): array
    {
        return $this->context[$template] ?? [];
    }

    public function hasTemplate(string $template): bool
    {
        return isset($this->context[$template]);
    }

    /**
     * Merges the context with the other context. When the other context contains
     * a template that is not in this context, it will be added to the changedTemplates list.
     *
     * When the templates are already in the context, the other contexts will be merged into it.
     * But only when the context value is not already defined, it will be added to the changedTemplates list.
     * The reason for this, is that it doesn't make sense to re-render a template when the context is the same.
     *
     * @param list<string> $changedTemplates
     */
    public function merge(self $other, array &$changedTemplates): self
    {
        $changed = [];
        $mergedContext = $this->context;
        foreach ($other->context as $template => $renderPoints) {
            if ( ! isset($mergedContext[$template])) {
                foreach ($renderPoints as $hash => [$sourceLocation, $context]) {
                    if (isset($mergedContext[$sourceLocation->fileName][$hash])) {
                        continue;
                    }

                    $mergedContext[$template][$hash] = [$sourceLocation, $context];
                    $changed[] = $template;
                }

                continue;
            }

            $flatContext = $this->getUniqueContextByTemplate($template);
            $uniqueContext = [];

            foreach ($renderPoints as $hash => [$sourceLocation, $context]) {
                if (isset($mergedContext[$template][$hash])) {
                    continue;
                }

                if ( ! isset($this->context[$sourceLocation->last()->fileName])) {
                    continue;
                }

                $mergedContext[$template][$hash] = [$sourceLocation, $context];
                $uniqueContext[] = $context;
            }

            $uniqueContext = array_values(array_unique($uniqueContext));

            if ($flatContext !== $uniqueContext) {
                $changed[] = $template;
            }
        }

        $changedTemplates = array_values(array_unique($changed));

        return new self($mergedContext);
    }
}

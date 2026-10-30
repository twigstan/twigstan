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
     * The arguments that macros are called with are stored as the context of this key.
     */
    public static function macroKey(string $template, string $macro): string
    {
        return sprintf('%s::%s', $template, $macro);
    }

    /**
     * Returns the template, for keys of templates and macros.
     */
    public static function templateFromKey(string $key): string
    {
        return explode('::', $key, 2)[0];
    }

    /**
     * @return array<string, list<string>> The unique contexts of each macro of the template
     */
    public function getUniqueContextByMacro(string $template): array
    {
        $prefix = self::macroKey($template, '');
        $contexts = [];
        foreach (array_keys($this->context) as $key) {
            if (str_starts_with($key, $prefix)) {
                $contexts[substr($key, strlen($prefix))] = $this->getUniqueContextByTemplate($key);
            }
        }

        return $contexts;
    }

    /**
     * @return list<string>
     */
    public function getUniqueContextByTemplate(string $template): array
    {
        $uniqueContext = [];
        foreach ($this->context[$template] ?? [] as [, $context]) {
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
     * Whether the template is rendered, or one of its macros is called.
     */
    public function hasTemplateOrMacros(string $template): bool
    {
        return $this->hasTemplate($template) || $this->getUniqueContextByMacro($template) !== [];
    }

    /**
     * Removes the contexts of templates that are included by templates without context,
     * because the variables of those templates are unknown, not missing.
     */
    public function withoutIncludesFromTemplatesWithoutContext(self $known): self
    {
        $context = [];
        foreach ($this->context as $template => $renderPoints) {
            foreach ($renderPoints as $hash => [$sourceLocation, $templateContext]) {
                // The last location is the template that was analyzed, the others are inlined parents.
                $includingTemplate = $sourceLocation->fileName;
                foreach ($sourceLocation as $location) {
                    $includingTemplate = $location->fileName;
                }

                if ( ! str_contains($template, '::') && ! $known->hasTemplateOrMacros($includingTemplate)) {
                    continue;
                }

                $context[$template][$hash] = [$sourceLocation, $templateContext];
            }
        }

        return new self($context);
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
            // Macro arguments are collected again on every run, so only arguments with new types change the template.
            if (str_contains($template, '::')) {
                $before = $this->getUniqueContextByTemplate($template);

                foreach ($renderPoints as $hash => $renderPoint) {
                    $mergedContext[$template][$hash] = $renderPoint;
                }

                if ($before !== (new self($mergedContext))->getUniqueContextByTemplate($template)) {
                    $changed[] = self::templateFromKey($template);
                }

                continue;
            }

            if ( ! isset($mergedContext[$template])) {
                foreach ($renderPoints as $hash => [$sourceLocation, $context]) {
                    if (isset($mergedContext[$sourceLocation->fileName][$hash])) {
                        continue;
                    }

                    $mergedContext[$template][$hash] = [$sourceLocation, $context];
                    $changed[] = self::templateFromKey($template);
                }

                continue;
            }

            $flatContext = $this->getUniqueContextByTemplate($template);
            $uniqueContext = [];

            foreach ($renderPoints as $hash => [$sourceLocation, $context]) {
                if (isset($mergedContext[$template][$hash])) {
                    if ($mergedContext[$template][$hash][1] === $context) {
                        continue;
                    }
                }

                if ( ! isset($this->context[$sourceLocation->last()->fileName])) {
                    continue;
                }

                $mergedContext[$template][$hash] = [$sourceLocation, $context];
                $uniqueContext[] = $context;
            }

            $uniqueContext = array_values(array_unique($uniqueContext));

            if ($flatContext !== $uniqueContext) {
                $changed[] = self::templateFromKey($template);
            }
        }

        $changedTemplates = array_values(array_unique($changed));

        return new self($mergedContext);
    }
}

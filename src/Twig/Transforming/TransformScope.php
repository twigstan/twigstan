<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming;

use PhpParser\Node;

final class TransformScope
{
    private const string DEFAULT_OUTPUT_VARIABLE = 'output';

    /**
     * @param array<string, string> $globals
     * @param array<string, string> $requirements
     * @param list<string> $assignedVariableNames
     * @param array<string, list<Node>> $blocks
     */
    public function __construct(
        private array $globals,
        private array $requirements,
        private null|self $parent = null,
        private string $outputVariable = self::DEFAULT_OUTPUT_VARIABLE,
        private int $contextIndex = 1,
        private array $assignedVariableNames = [],
        private array $blocks = [],
    ) {}

    public function assignVariableName(string $name): void
    {
        if (in_array($name, $this->assignedVariableNames, true)) {
            return;
        }

        $this->assignedVariableNames[] = $name;
    }

    /**
     * @return list<string>
     */
    public function getAllVariableNames(): array
    {
        return array_unique([
            ...array_keys($this->globals),
            ...array_map(
                fn(string $type) => rtrim($type, '?'),
                array_keys($this->requirements),
            ),
            ...$this->assignedVariableNames,
        ]);
    }

    /**
     * @return list<string>
     */
    public function getAssignedVariableNames(): array
    {
        return $this->assignedVariableNames;
    }

    /**
     * @return static
     */
    public function withOutputVariable(string $outputVariable): self
    {
        return new self(
            $this->globals,
            $this->requirements,
            $this->parent,
            $outputVariable,
            $this->contextIndex,
            $this->assignedVariableNames,
        );
    }

    /**
     * @return static
     */
    public function enterScope(): self
    {
        return new self(
            $this->globals,
            $this->requirements,
            $this,
            $this->outputVariable,
            $this->contextIndex + 1,
            [],
        );
    }

    public function getOutputVariable(): string
    {
        return $this->outputVariable;
    }

    public function getContextVariable(): string
    {
        if ($this->contextIndex === 1) {
            return 'context';
        }

        return sprintf('context%d', $this->contextIndex);
    }

    /**
     * @return array<string, string>
     */
    public function getRequirements(bool $withGlobals = true): array
    {
        return array_merge(
            $withGlobals ? $this->globals : [],
            $this->requirements,
        );
    }

    /**
     * @param array<string, string> $requirements
     *
     * @return static
     */
    public function withRequirements(array $requirements): self
    {
        return new self(
            $this->globals,
            $requirements,
            $this->parent,
            $this->outputVariable,
            $this->contextIndex,
            $this->assignedVariableNames,
        );
    }

    public function hasVariableName(string $string): bool
    {
        if (in_array($string, array_keys($this->globals), true)) {
            return true;
        }

        if (in_array($string, array_keys($this->requirements), true)) {
            return true;
        }

        return in_array($string, $this->assignedVariableNames, true);
    }

    /**
     * @param array<string, list<Node>> $blocks
     *
     */
    public function withBlocks(array $blocks): self
    {
        return new self(
            $this->globals,
            $this->requirements,
            $this->parent,
            $this->outputVariable,
            $this->contextIndex,
            $this->assignedVariableNames,
            $blocks,
        );
    }

    /**
     * @return list<Node>
     */
    public function getBlock(string $name): array
    {
        return $this->blocks[$name] ?? $this->parent?->getBlock($name) ?? [];
    }
}

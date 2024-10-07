<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use Stringable;
use Twig\Environment;
use Twig\Source;
use Twig\Template;

abstract class SimplifiedTwigTemplate
{
    protected Source $source;
    protected Environment $env;

    /**
     * @var array<string, Template>
     */
    protected array $macros;

    /**
     * @param array<mixed> $context
     * @param array<mixed> $blocks
     * @return iterable<null|scalar|Stringable>
     */
    public function yieldTemplate(
        array $context,
        string $template,
        ?string $templateName = null,
        ?int $line = null,
        ?int $index = null,
        array $blocks = [],
    ): iterable {
        yield from [];
    }

    /**
     * @param array<mixed> $context
     * @param array<mixed> $blocks
     * @return iterable<null|scalar|Stringable>
     */
    public function yieldBlock(
        string $name,
        array $context,
        array $blocks = [],
        bool $useBlocks = true,
        ?self $templateContext = null,
    ): iterable {
        yield from [];
    }

    /**
     * @param array<mixed> $context
     * @param array<mixed> $blocks
     * @return iterable<null|scalar|Stringable>
     */
    public function yieldParentBlock(
        string $name,
        array $context,
        array $blocks = [],
    ): iterable {
        yield from [];
    }

    /**
     * @param array<mixed> $args
     * @param array<mixed> $context
     */
    public function callMacro(
        string $template,
        string $method,
        array $args,
        int $lineno,
        array $context,
    ): mixed {
        return '';
    }

    /**
     * @param array<mixed> $context
     * @param list<string>|string $template
     * @param array<mixed> $variables
     */
    public function include(
        array $context,
        array | string $template,
        array $variables = [],
        bool $withContext = true,
        bool $ignoreMissing = false,
        bool $sandboxed = false,
    ): string {
        return '';
    }
}

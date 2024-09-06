<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use Stringable;
use Twig\Environment;
use Twig\Source;

abstract class SimplifiedTwigTemplate
{
    protected Source $source;
    protected Environment $env;

    public function yieldTemplate(array $context, $template, $templateName = null, $line = null, $index = null, array $blocks = []): iterable {}

    /**
     * @return iterable<null|scalar|Stringable>
     */
    public function yieldBlock($name, array $context, array $blocks = [], $useBlocks = true, ?self $templateContext = null): iterable {}

    /**
     * @return iterable<null|scalar|Stringable>
     */
    public function yieldParentBlock($name, array $context, array $blocks = []): iterable {}

    public function callMacro(string $template, string $method, array $args, int $lineno, array $context) {}

    public function include(array $context, string $template, array $variables = [], bool $withContext = true, bool $ignoreMissing = false, bool $sandboxed = false): string {}

}

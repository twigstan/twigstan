<?php

declare(strict_types=1);

function twigstan_type_hint(array &$context, string $name, string $type, bool $optional): void {}

/**
 * Marks the arguments of a call to a macro, so their types can be collected.
 *
 * @param array<mixed> $arguments
 *
 * @return array<mixed>
 */
function twigstan_macro_arguments(string $template, string $macro, array $arguments): array
{
    return $arguments;
}

/**
 * Marks where an embedded template, such as {% embed %} or a component, is rendered, so the context of its blocks can be collected.
 *
 * @param array<mixed> $context
 *
 * @return iterable<never>
 */
function twigstan_embed(int $index, array $context): iterable
{
    return [];
}

/**
 * Marks where a Symfony UX Twig component is rendered with `{% component %}` or `<twig:Name>...</twig:Name>`, see SimpleComponentNode.
 * Returns the variables that the component adds to the context of its body, see ComponentRenderer::preRender().
 *
 * @param array<mixed> $context
 * @param array<mixed> $props
 *
 * @return array{this: object, attributes: Symfony\UX\TwigComponent\ComponentAttributes, outerScope: array<mixed>}
 */
function twigstan_component(array $context, string $name, array $props): array
{
    throw new LogicException('Only used for static analysis.');
}

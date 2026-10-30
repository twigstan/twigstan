<?php

declare(strict_types=1);

function twigstan_type_hint(array &$context, string $name, string $type, bool $optional): void {}

/**
 * Marks the arguments of a call to a macro, so their types can be collected.
 *
 * @template TArguments of array
 *
 * @param TArguments $arguments
 *
 * @return TArguments
 */
function twigstan_macro_arguments(string $template, string $macro, array $arguments): array
{
    return $arguments;
}

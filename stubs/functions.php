<?php

function twigstan_type_hint(string $type) {}

function twigstan_extends(string $template, array $context): void {}

function twigstan_has_block(string $bock): bool {}

/**
 * @param array<mixed> $context
 * @param array<mixed> $variables
 */
function twigstan_include(string $template, array $context, array $variables, bool $only): void {}

/**
 * @template T of object
 * @param class-string<T> $class
 *
 * @return T
 */
function twigstan_get_object(string $class): object {}

<?php

declare(strict_types=1);

namespace TwigStan\PHP;

use PHPStan\Rules\RuleError;

final readonly class ExpressionToStringResult
{
    /**
     * @param list<string> $values
     * @param list<RuleError> $errors
     */
    private function __construct(
        public array $values = [],
        public array $errors = [],
    ) {}

    public static function error(RuleError $error): self
    {
        return new self(
            [],
            [$error],
        );
    }

    public static function create(self ...$others): self
    {
        $result = new self();

        foreach ($others as $other) {
            $result = $result->and($other);
        }

        return $result;
    }

    public static function value(string ...$values): self
    {
        return new self(array_values($values));
    }

    /**
     * @return static
     */
    public function and(self $other): self
    {
        return new self(
            [...$this->values, ...$other->values],
            [...$this->errors, ...$other->errors],
        );
    }

    /**
     * @param callable(string): string $callable
     *
     * @return self
     */
    public function map(callable $callable): self
    {
        return new self(
            array_map($callable, $this->values),
            $this->errors,
        );
    }
}

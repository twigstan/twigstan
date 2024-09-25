<?php

declare(strict_types=1);

namespace TwigStan\Error\Baseline;

use TwigStan\Error\BaselineError;

interface BaselineDumper
{
    /**
     * @param list<BaselineError> $errors
     */
    public function dump(array $errors): string;
}

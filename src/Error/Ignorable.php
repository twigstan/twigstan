<?php

declare(strict_types=1);

namespace TwigStan\Error;

use TwigStan\PHPStan\Analysis\Error;

interface Ignorable
{
    public function shouldIgnore(Error $error): bool;
}

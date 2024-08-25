<?php

declare(strict_types=1);

namespace TwigStan\Twig\Requirements;

use Exception;

final class RequirementsNotFoundException extends Exception
{
    public static function create(): self
    {
        return new self('Requirements not found');
    }
}

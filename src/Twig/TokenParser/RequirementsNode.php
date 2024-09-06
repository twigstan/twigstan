<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Attribute\YieldReady;
use Twig\Node\Node;

#[YieldReady]
final class RequirementsNode extends Node
{
    /**
     * @param array<string, array{type: string, optional: bool}> $requirements
     */
    public function __construct(array $requirements, int $line)
    {
        parent::__construct([], ['requirements' => $requirements], $line);
    }
}

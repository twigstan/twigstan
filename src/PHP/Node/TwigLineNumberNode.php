<?php

declare(strict_types=1);

namespace TwigStan\PHP\Node;

use PhpParser\Node\Stmt\Nop;

final class TwigLineNumberNode extends Nop
{
    public function __construct(int $line)
    {
        parent::__construct(['line' => $line]);
    }

    public function getType(): string
    {
        return 'TwigLineNumber';
    }
}

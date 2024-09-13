<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

use ReflectionProperty;
use Twig\Compiler;
use Twig\Node\Node;

final class ModifiedCompiler extends Compiler
{
    private ReflectionProperty $reflector;

    public function addDebugInfo(Node $node): self
    {
        $this->reflector ??= new ReflectionProperty(Compiler::class, 'lastLine');

        $lastLine = $this->reflector->getValue($this);
        $this->reflector->setValue($this, 0);

        try {
            return parent::addDebugInfo($node);
        } finally {
            $this->reflector->setValue($this, $lastLine);
        }
    }
}

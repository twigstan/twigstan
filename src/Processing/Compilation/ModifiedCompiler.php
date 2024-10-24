<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

use ReflectionProperty;
use Twig\Compiler;
use Twig\Node\Expression\NameExpression;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;

final class ModifiedCompiler extends Compiler
{
    private ReflectionProperty $reflector;

    public function addDebugInfo(Node $node): self
    {
        // TODO: twig/twig:v3.15.0 Remove NameExpression check and bump minimum required Twig version to 3.15
        // @phpstan-ignore class.notFound
        if ($node instanceof NameExpression || $node instanceof ContextVariable) {
            return $this;
        }

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

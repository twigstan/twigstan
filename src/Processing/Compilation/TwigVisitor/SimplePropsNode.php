<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\TwigVisitor;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Replaces the Symfony UX PropsNode (`{% props %}`).
 * The original also removes every attribute from the context in a loop, which makes every
 * variable optional for PHPStan. This node only keeps what matters for the types:
 * a prop without a value gets its default, and a prop without a default is required.
 *
 * Since symfony/ux-twig-component 3.5, a prop that is passed as null keeps null instead of getting its default.
 */
#[YieldReady]
final class SimplePropsNode extends Node
{
    public static function create(Node $node): self
    {
        $defaults = [];
        foreach ($node as $name => $default) {
            $defaults[$name] = $default;
        }

        return new self(
            $defaults,
            [
                'names' => $node->getAttribute('names'),
                // PropsNode::getPropDocumentation() was added in the same release, 3.5.
                'keep_null' => method_exists($node, 'getPropDocumentation'),
            ],
            $node->getTemplateLine(),
        );
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        foreach ($this->getAttribute('names') as $name) {
            if ($this->getAttribute('keep_null') === true) {
                $compiler
                    ->write('if (!\\array_key_exists(')
                    ->string($name)
                    ->raw(', $context)) {' . "\n");
            } else {
                $compiler
                    ->write('if (!isset($context[')
                    ->string($name)
                    ->raw("])) {\n");
            }

            $compiler->indent();

            if ($this->hasNode($name)) {
                $compiler
                    ->write('$context[')
                    ->string($name)
                    ->raw('] = ')
                    ->subcompile($this->getNode($name))
                    ->raw(";\n");
            } else {
                $compiler
                    ->write('throw new \Twig\Error\RuntimeError(')
                    ->string(sprintf('%s should be defined for component %s.', $name, $this->getTemplateName()))
                    ->raw(");\n");
            }

            $compiler
                ->outdent()
                ->write("}\n");
        }
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use Symfony\UX\TwigComponent\Twig\PropsNode;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Node\Node as TwigNode;
use TwigStan\PHPStan\TwigComponentCallResolver;
use TwigStan\Processing\Compilation\TwigVisitor\SimplePropsNode;

/**
 * Reports where an anonymous Symfony UX Twig component is rendered without a prop that its `{% props %}` tag declares
 * without a default. Symfony UX throws a RuntimeError for those.
 *
 * @implements Rule<Node\Stmt\Expression>
 */
#[Conditional(attribute: new AutowiredService(), if: '%twigstan.phases.analysis%')]
final class TwigComponentRequiredPropsRule implements Rule
{
    /**
     * @var array<string, list<string>>
     */
    private array $requiredPropsByTemplate = [];

    public function __construct(
        private readonly TwigComponentCallResolver $twigComponentCallResolver,
        private readonly Environment $twig,
    ) {}

    public function getNodeType(): string
    {
        return Node\Stmt\Expression::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $call = $this->twigComponentCallResolver->resolve($node, $scope);

        if ($call === null) {
            return [];
        }

        $errors = [];
        foreach ($call['templates'] as $name => $template) {
            foreach ($this->getRequiredProps($template) as $prop) {
                $key = new ConstantStringType($prop);

                // See ComponentRenderer::preRender(): the props are merged into the context outside the component.
                if ( ! $call['props']->hasOffsetValueType($key)->no() || ! $call['context']->hasOffsetValueType($key)->no()) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf('Component "%s" is rendered without its required prop "%s".', $name, $prop))
                    ->identifier('twigComponent.missingProp')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function getRequiredProps(string $template): array
    {
        if (isset($this->requiredPropsByTemplate[$template])) {
            return $this->requiredPropsByTemplate[$template];
        }

        try {
            $module = $this->twig->parse($this->twig->tokenize($this->twig->getLoader()->getSourceContext($template)));
        } catch (Error) {
            // TwigStan reports templates that Twig cannot load or compile where they are analysed.
            return $this->requiredPropsByTemplate[$template] = [];
        }

        $props = $this->findProps($module);

        if ($props === null) {
            return $this->requiredPropsByTemplate[$template] = [];
        }

        $required = [];
        foreach ($props->getAttribute('names') as $prop) {
            if ( ! $props->hasNode($prop)) {
                $required[] = $prop;
            }
        }

        return $this->requiredPropsByTemplate[$template] = $required;
    }

    private function findProps(TwigNode $node): ?TwigNode
    {
        foreach ($node as $child) {
            if ($child instanceof PropsNode || $child instanceof SimplePropsNode) {
                return $child;
            }

            $found = $this->findProps($child);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}

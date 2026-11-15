<?php

declare(strict_types=1);

namespace TwigStan\PHPStan;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\ObjectType;
use Symfony\UX\TwigComponent\ComponentTemplateFinder;
use Symfony\UX\TwigComponent\Twig\ComponentRuntime;
use Twig\Environment;

/**
 * Finds where a compiled template renders a Symfony UX Twig component:
 * - `<twig:Name>...</twig:Name>` or `{% component %}`, compiled by SimpleComponentNode to:
 *       yield from \twigstan_embed(123, [..., ...\twigstan_component($context, "Name", ["prop" => "value"])]);
 * - `<twig:Name />` or `{{ component() }}`, compiled to:
 *       yield $this->env->getRuntime('Symfony\UX\TwigComponent\Twig\ComponentRuntime')->render("Name", ["prop" => "value"]);
 */
#[AutowiredService]
final readonly class TwigComponentCallResolver
{
    public function __construct(
        private Environment $twig,
    ) {}

    /**
     * @return null|array{
     *     context: ConstantArrayType,
     *     props: ConstantArrayType,
     *     templates: non-empty-array<string, string>,
     * } The context outside the component that it receives, the props, and the anonymous template of each possible component name
     */
    public function resolve(Node\Stmt\Expression $node, Scope $scope): ?array
    {
        $call = $this->findComponentCall($node);

        if ($call instanceof Node\Expr\FuncCall) {
            $args = $call->getArgs();

            if (count($args) !== 3) {
                return null;
            }

            $contexts = $scope->getType($args[0]->value)->getConstantArrays();
            $nameArg = $args[1];
            $propsArg = $args[2];
        } elseif ($call instanceof Node\Expr\MethodCall && (new ObjectType(ComponentRuntime::class))->isSuperTypeOf($scope->getType($call->var))->yes()) {
            $args = $call->getArgs();

            if ( ! isset($args[0])) {
                return null;
            }

            $contexts = [new ConstantArrayType([], [])];
            $nameArg = $args[0];
            $propsArg = $args[1] ?? null;
        } else {
            return null;
        }

        if (count($contexts) !== 1) {
            return null;
        }

        $props = $propsArg !== null ? $scope->getType($propsArg->value)->getConstantArrays() : [new ConstantArrayType([], [])];

        if (count($props) !== 1) {
            return null;
        }

        $finder = new ComponentTemplateFinder($this->twig->getLoader(), 'components');

        $templates = [];
        foreach ($scope->getType($nameArg->value)->getConstantStrings() as $name) {
            $template = $finder->findAnonymousComponentTemplate($name->getValue());

            if ($template !== null) {
                $templates[$name->getValue()] = $template;
            }
        }

        if ($templates === []) {
            return null;
        }

        return [
            'context' => $contexts[0],
            'props' => $props[0],
            'templates' => $templates,
        ];
    }

    private function findComponentCall(Node\Stmt\Expression $node): null | Node\Expr\FuncCall | Node\Expr\MethodCall
    {
        if ($node->expr instanceof Node\Expr\Yield_ && $node->expr->value instanceof Node\Expr\MethodCall) {
            $call = $node->expr->value;

            return $call->name instanceof Node\Identifier && $call->name->name === 'render' ? $call : null;
        }

        if ( ! $node->expr instanceof Node\Expr\YieldFrom) {
            return null;
        }

        $call = (new NodeFinder())->findFirst(
            $node->expr,
            fn(Node $node) => $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && $node->name->toString() === 'twigstan_component',
        );

        return $call instanceof Node\Expr\FuncCall ? $call : null;
    }
}

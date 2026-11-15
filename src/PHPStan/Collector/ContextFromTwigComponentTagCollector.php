<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use LogicException;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use Symfony\UX\TwigComponent\AnonymousComponent;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComputedPropertiesProxy;
use TwigStan\PHPStan\TwigComponentCallResolver;
use TwigStan\Twig\CommentHelper;

/**
 * Collects the context for the templates of anonymous Symfony UX Twig components, where they are rendered from a template.
 *
 * Templates of class components get their context from ContextFromTwigComponentClassCollector instead.
 *
 * @implements TemplateContextCollector<Node\Stmt\Expression>
 */
#[Conditional(attribute: new AutowiredService(), if: '%twigstan.phases.analysis%')]
final readonly class ContextFromTwigComponentTagCollector implements TemplateContextCollector
{
    public function __construct(
        private TwigComponentCallResolver $twigComponentCallResolver,
    ) {}

    public function getNodeType(): string
    {
        return Node\Stmt\Expression::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $call = $this->twigComponentCallResolver->resolve($node, $scope);

        if ($call === null) {
            return null;
        }

        $sourceLocation = null;
        foreach ($node->getComments() as $comment) {
            $sourceLocation = CommentHelper::getSourceLocationFromComment($comment->getText());

            if ($sourceLocation !== null) {
                break;
            }
        }

        if ($sourceLocation === null) {
            throw new LogicException(sprintf('Could not find Twig line number on %s:%d.', $scope->getFile(), $node->getStartLine()));
        }

        // See ComponentRenderer::preRender()
        $builder = ConstantArrayTypeBuilder::createFromConstantArray($call['context']);

        foreach ($call['props']->getKeyTypes() as $key) {
            $builder->setOffsetValueType($key, $call['props']->getOffsetValueType($key));
        }

        $builder->setOffsetValueType(new ConstantStringType('attributes'), new ObjectType(ComponentAttributes::class));
        $builder->setOffsetValueType(new ConstantStringType('this'), new ObjectType(AnonymousComponent::class));
        $builder->setOffsetValueType(new ConstantStringType('computed'), new ObjectType(ComputedPropertiesProxy::class));
        $builder->setOffsetValueType(new ConstantStringType('outerScope'), new ArrayType(new StringType(), new MixedType()));

        $context = (new Printer())->print($builder->getArray()->toPhpDocNode());

        $result = [];
        foreach ($call['templates'] as $template) {
            $result[] = [
                'sourceLocation' => $sourceLocation,
                'template' => $template,
                'context' => $context,
            ];
        }

        return $result;
    }
}

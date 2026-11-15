<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Node\InClassNode;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Reflection\AttributeReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use ReflectionProperty;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComputedPropertiesProxy;
use TwigStan\Twig\SourceLocation;

/**
 * Every class with the #[AsTwigComponent] attribute is a render point for its template.
 * The context mirrors what Symfony UX passes to the template, see ComponentRenderer::preRender().
 *
 * @implements TemplateContextCollector<InClassNode>
 */
#[Conditional(attribute: new AutowiredService(), if: '%twigstan.phases.templateContextCollecting%')]
final readonly class ContextFromTwigComponentClassCollector implements TemplateContextCollector
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {}

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $classReflection = $node->getClassReflection();

        $attribute = $this->findAsTwigComponentAttribute($classReflection);

        if ($attribute === null) {
            return null;
        }

        $arguments = $attribute->getArgumentTypes();

        $template = $this->getConstantString($arguments, 'template');

        if ($template === null) {
            if (isset($arguments['template'])) {
                // The template is resolved at runtime, using FromMethod.
                return null;
            }

            $name = $this->getConstantString($arguments, 'name') ?? $classReflection->getNativeReflection()->getShortName();
            $template = sprintf('components/%s.html.twig', str_replace(':', '/', $name));
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();

        $exposePublicProps = $arguments['exposePublicProps'] ?? null;

        if ($exposePublicProps === null || ! $exposePublicProps->isFalse()->yes()) {
            foreach ($classReflection->getNativeReflection()->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $builder->setOffsetValueType(
                    new ConstantStringType($property->getName()),
                    $classReflection->getNativeProperty($property->getName())->getReadableType(),
                );
            }
        }

        foreach ($classReflection->getNativeReflection()->getProperties() as $property) {
            $name = $this->getExposedName(($property->getAttributes(ExposeInTemplate::class)[0] ?? null)?->getArguments(), $property->getName());

            if ($name === null) {
                continue;
            }

            $builder->setOffsetValueType(
                new ConstantStringType($name),
                $classReflection->getNativeProperty($property->getName())->getReadableType(),
            );
        }

        foreach ($classReflection->getNativeReflection()->getMethods() as $method) {
            if ( ! $method->isPublic()) {
                continue;
            }

            $defaultName = str_starts_with($method->getName(), 'get') ? lcfirst(substr($method->getName(), 3)) : $method->getName();
            $name = $this->getExposedName(($method->getAttributes(ExposeInTemplate::class)[0] ?? null)?->getArguments(), $defaultName);

            if ($name === null) {
                continue;
            }

            $builder->setOffsetValueType(
                new ConstantStringType($name),
                ParametersAcceptorSelector::selectFromArgs($scope, [], $classReflection->getNativeMethod($method->getName())->getVariants())->getReturnType(),
            );
        }

        $builder->setOffsetValueType(
            new ConstantStringType($this->getConstantString($arguments, 'attributesVar') ?? 'attributes'),
            new ObjectType(ComponentAttributes::class),
        );
        $builder->setOffsetValueType(new ConstantStringType('this'), new ObjectType($classReflection->getName()));
        $builder->setOffsetValueType(new ConstantStringType('computed'), new ObjectType(ComputedPropertiesProxy::class));
        $builder->setOffsetValueType(new ConstantStringType('outerScope'), new ArrayType(new StringType(), new MixedType()));

        return [
            [
                'sourceLocation' => new SourceLocation($scope->getFile(), $this->getAttributeLine($node, $attribute)),
                'template' => $template,
                'context' => (new Printer())->print($builder->getArray()->toPhpDocNode()),
            ],
        ];
    }

    private function findAsTwigComponentAttribute(ClassReflection $classReflection): ?AttributeReflection
    {
        foreach ($classReflection->getAttributes() as $attribute) {
            // Also matches attributes that extend it, such as #[AsLiveComponent].
            if (
                $this->reflectionProvider->hasClass($attribute->getName())
                && $this->reflectionProvider->getClass($attribute->getName())->is(AsTwigComponent::class)
            ) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @param array<string, \PHPStan\Type\Type> $arguments
     */
    private function getConstantString(array $arguments, string $name): ?string
    {
        if ( ! isset($arguments[$name])) {
            return null;
        }

        $strings = $arguments[$name]->getConstantStrings();

        if (count($strings) !== 1) {
            return null;
        }

        return $strings[0]->getValue();
    }

    /**
     * Returns the variable name for an #[ExposeInTemplate] attribute, or null when it is not exposed.
     * Destructured values have unknown names, so they are skipped.
     *
     * @param null|array<int|string, mixed> $arguments The arguments of the attribute, or null when there is none.
     */
    private function getExposedName(?array $arguments, string $defaultName): ?string
    {
        if ($arguments === null) {
            return null;
        }

        if (($arguments['destruct'] ?? $arguments[2] ?? false) === true) {
            return null;
        }

        $name = $arguments['name'] ?? $arguments[0] ?? null;

        return is_string($name) ? $name : $defaultName;
    }

    private function getAttributeLine(InClassNode $node, AttributeReflection $attribute): int
    {
        foreach ($node->getOriginalNode()->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($attr->name->toString() === $attribute->getName()) {
                    return $attr->getStartLine();
                }
            }
        }

        return $node->getOriginalNode()->getStartLine();
    }
}

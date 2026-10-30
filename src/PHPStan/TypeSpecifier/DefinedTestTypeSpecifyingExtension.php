<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\TypeSpecifier;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\NullType;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use Twig\Extension\CoreExtension;

/**
 * When `a.b.c is defined` is true, which is how `a.b.c|default` starts, a and a.b are not null.
 *
 * The defined test reads a.b while ignoring the strict check, but the then branch reads it as usual,
 * so both expressions are narrowed.
 */
final class DefinedTestTypeSpecifyingExtension implements StaticMethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private const int IS_DEFINED_TEST = 6;

    private const int IGNORE_STRICT_CHECK = 7;

    private TypeSpecifier $typeSpecifier;

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function getClass(): string
    {
        return CoreExtension::class;
    }

    public function isStaticMethodSupported(MethodReflection $staticMethodReflection, StaticCall $node, TypeSpecifierContext $context): bool
    {
        return $staticMethodReflection->getName() === 'getAttribute'
            && $context->true()
            && self::isGetAttributeCall($node)
            && $node->getArgs()[self::IS_DEFINED_TEST]->value instanceof ConstFetch
            && $node->getArgs()[self::IS_DEFINED_TEST]->value->name->toLowerString() === 'true';
    }

    public function specifyTypes(MethodReflection $staticMethodReflection, StaticCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        $specifiedTypes = new SpecifiedTypes();

        $object = $node->getArgs()[2]->value;
        while (true) {
            $objects = [$object];

            if ($object instanceof StaticCall && self::isGetAttributeCall($object)) {
                $objects[] = self::withoutDefinedTest($object);
            }

            foreach ($objects as $expr) {
                $specifiedTypes = $specifiedTypes->unionWith(
                    $this->typeSpecifier->create($expr, new NullType(), TypeSpecifierContext::createFalse(), $scope),
                );
            }

            if ( ! $object instanceof StaticCall || ! self::isGetAttributeCall($object)) {
                return $specifiedTypes;
            }

            $object = $object->getArgs()[2]->value;
        }
    }

    private static function isGetAttributeCall(StaticCall $node): bool
    {
        return $node->class instanceof FullyQualified
            && $node->class->toString() === CoreExtension::class
            && $node->name instanceof Identifier
            && $node->name->name === 'getAttribute'
            && count($node->getArgs()) > self::IGNORE_STRICT_CHECK;
    }

    /**
     * The same read of the attribute, as it is compiled outside the defined test.
     */
    private static function withoutDefinedTest(StaticCall $node): StaticCall
    {
        $args = $node->getArgs();
        $args[self::IS_DEFINED_TEST] = new Arg(new ConstFetch(new FullyQualified('false')));
        $args[self::IGNORE_STRICT_CHECK] = new Arg(new ConstFetch(new FullyQualified('false')));

        $object = $args[2]->value;

        if ($object instanceof StaticCall && self::isGetAttributeCall($object)) {
            $args[2] = new Arg(self::withoutDefinedTest($object));
        }

        return new StaticCall($node->class, $node->name, $args);
    }
}

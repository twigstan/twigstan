<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredParameter;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Symfony\Bridge\Twig\AppVariable;
use Twig\Environment;

#[Conditional(attribute: new AutowiredService(), if: 'not(%twigstan.phases.templateContextCollecting%)')]
final readonly class GetGlobalsReturnType implements DynamicMethodReturnTypeExtension
{
    public function __construct(
        private Environment $twig,
        #[AutowiredParameter(ref: '%twigstan.appUserClass%')]
        private ?string $appUserClass,
    ) {}

    public function getClass(): string
    {
        return Environment::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getGlobals';
    }

    public function getTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type {
        $builder = ConstantArrayTypeBuilder::createEmpty();
        $builder->disableArrayDegradation();

        foreach ($this->twig->getGlobals() as $key => $value) {
            $builder->setOffsetValueType(new ConstantStringType($key), $this->getType($value));
        }

        $globals = $builder->getArray()->getConstantArrays();
        assert(count($globals) === 1);

        return $globals[0]->withPreservedArrayShape();
    }

    private function getType(mixed $value): Type
    {
        if ($value === null) {
            return new NullType();
        }

        if (is_int($value)) {
            return new IntegerType();
        }

        if (is_float($value)) {
            return new FloatType();
        }

        if (is_bool($value)) {
            return new BooleanType();
        }

        if (is_string($value)) {
            return new StringType();
        }

        // The user is null when the route does not require one, or when the template is rendered outside a request.
        if ($value instanceof AppVariable && $this->appUserClass !== null) {
            return new GenericObjectType($value::class, [TypeCombinator::addNull(new ObjectType($this->appUserClass))]);
        }

        if (is_object($value)) {
            return new ObjectType($value::class);
        }

        if (is_array($value)) {
            $arrayBuilder = ConstantArrayTypeBuilder::createEmpty();

            if (count($value) > ConstantArrayTypeBuilder::ARRAY_COUNT_LIMIT) {
                $arrayBuilder->degradeToGeneralArray(true);
            }

            foreach ($value as $k => $v) {
                $arrayBuilder->setOffsetValueType(
                    $this->getType($k),
                    $this->getType($v),
                );
            }

            return $arrayBuilder->getArray();
        }

        return new MixedType();
    }
}

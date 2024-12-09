<?php

declare(strict_types=1);

namespace TwigStan\PHPStan;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Arrays\NonexistentOffsetInArrayDimFetchCheck;
use PHPStan\Rules\FunctionCallParametersCheck;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Methods\MethodCallCheck;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Enum\EnumCaseObjectType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use Twig\Template;

final readonly class GetAttributeCheck
{
    public function __construct(
        private MethodCallCheck $methodCallCheck,
        private FunctionCallParametersCheck $parametersCheck,
        private NonexistentOffsetInArrayDimFetchCheck $nonexistentOffsetInArrayDimFetchCheck,
        private ReflectionProvider $reflectionProvider,
    ) {}

    /**
     * @return null|array{Type, list<IdentifierRuleError>}
     */
    public function check(StaticCall $node, Scope $scope): ?array
    {
        $errors = [];

        $arguments = $this->getNormalizedArguments($node);

        if ($arguments === null) {
            return null;
        }

        $objectType = $scope->getType($arguments['object']);

        if ($objectType instanceof MixedType) {
            return [new MixedType(), $errors];
        }

        $propertyOrMethodType = $scope->getType($arguments['item']);

        if ($propertyOrMethodType instanceof ConstantIntegerType) {
            $propertyOrMethod = $propertyOrMethodType->getValue();
        } else {
            $constantStringTypes = $propertyOrMethodType->getConstantStrings();

            if ($constantStringTypes === []) {
                return [new MixedType(), $errors];
            }

            $propertyOrMethod = $constantStringTypes[0]->getValue();
        }

        $type = Template::ANY_CALL;

        if ($arguments['type'] !== null) {
            $typeStrings = $scope->getType($arguments['type'])->getConstantScalarTypes();

            if (count($typeStrings) !== 1) {
                return [new MixedType(), $errors];
            }

            $type = $typeStrings[0]->getValue();
        }

        // vendor/twig/twig/src/Extension/CoreExtension.php:1643
        if ($type !== Template::METHOD_CALL) {
            if ($objectType->isConstantArray()->yes()) {
                if ($objectType->hasOffsetValueType($propertyOrMethodType)->yes()) {
                    return [$objectType->getOffsetValueType($propertyOrMethodType), $errors];
                }
            }
        }

        // vendor/twig/twig/src/Extension/CoreExtension.php:1704
        if ( ! $objectType->isObject()->yes()) {
            if ($objectType->isNull()->yes()) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Cannot get "%s" on null.',
                    $propertyOrMethod,
                ))->identifier('getAttribute.null')->build();

                return [new ErrorType(), $errors];
            }

            if ($objectType->isArray()->yes()) {
                return [$objectType->getOffsetValueType($propertyOrMethodType), [
                    ...$errors,
                    // @phpstan-ignore phpstanApi.method
                    ...$this->nonexistentOffsetInArrayDimFetchCheck->check(
                        $scope,
                        new TypeExpr($objectType),
                        sprintf('Access to offset %s on an unknown class %%s.', $propertyOrMethodType->describe(VerbosityLevel::value())),
                        $propertyOrMethodType,
                    ),
                ]];
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Cannot get "%s" on %s.',
                $propertyOrMethod,
                $objectType->describe(VerbosityLevel::value()),
            ))->identifier('getAttribute.unknown')->build();

            return [new ErrorType(), $errors];
        }

        if (in_array($type, [Template::ANY_CALL, Template::ARRAY_CALL], true)) {
            if ($objectType->isArray()->yes()) {
                return [
                    $objectType->getOffsetValueType($propertyOrMethodType),
                    $errors,
                ];
            }
        }

        if ($objectType->isNull()->maybe()) {
            return [new ErrorType(), $errors];
        }

        // if (is_int($propertyOrMethod)) {
        //    return new ErrorType(); // @todo prob array?
        // }

        // object property
        // vendor/twig/twig/src/Extension/CoreExtension.php:1728
        if ($type !== Template::METHOD_CALL) {
            if ($objectType->hasProperty((string) $propertyOrMethod)->yes()) {
                $property = $objectType->getProperty((string) $propertyOrMethod, $scope);

                if ($property->isPublic()) {
                    return [$property->getReadableType(), $errors];
                }
            }

            // if ($object instanceof \DateTimeInterface && \in_array($item, ['date', 'timezone', 'timezone_type'], true)) {
            //                if ($isDefinedTest) {
            //                    return true;
            //                }
            //
            //                return ((array) $object)[$item];
            //            }

            if ($objectType->isEnum()->yes()) {
                $className = $objectType->getObjectClassNames()[0];
                $classReflection = $this->reflectionProvider->getClass($className);

                if ($classReflection->hasEnumCase((string) $propertyOrMethod)) {
                    return [
                        new EnumCaseObjectType($className, (string) $propertyOrMethod, $classReflection),
                        $errors,
                    ];
                }
            }

            if ($objectType->hasConstant((string) $propertyOrMethod)->yes()) {
                return [
                    $scope->getType($objectType->getConstant((string) $propertyOrMethod)->getValueExpr()),
                    $errors,
                ];
            }
        }

        if (in_array($type, [Template::ANY_CALL, Template::METHOD_CALL], true)) {
            foreach (['', 'get', 'is', 'has'] as $prefix) {
                if ( ! $objectType->hasMethod($prefix . $propertyOrMethod)->yes()) {
                    continue;
                }

                $methodName = $prefix . $propertyOrMethod;
                // @phpstan-ignore phpstanApi.method
                [, $methodReflection] = $this->methodCallCheck->check($scope, $methodName, new TypeExpr($objectType));

                if ($methodReflection === null) {
                    continue;
                }

                $declaringClass = $methodReflection->getDeclaringClass();
                $messagesMethodName = sprintf($declaringClass->getDisplayName() . '::' . $methodReflection->getName() . '()');

                $parametersAcceptor = ParametersAcceptorSelector::selectFromArgs(
                    $scope,
                    $arguments['args'],
                    $methodReflection->getVariants(),
                    $methodReflection->getNamedArgumentsVariants(),
                );

                return [
                    $parametersAcceptor->getReturnType(),
                    [
                        ...$errors,
                        // @phpstan-ignore phpstanApi.method
                        ...$this->parametersCheck->check(
                            $parametersAcceptor,
                            $scope,
                            $declaringClass->isBuiltin(),
                            new Expr\MethodCall(
                                new TypeExpr($objectType),
                                new Identifier($methodReflection->getName()),
                                $arguments['args'],
                            ),
                            'method',
                            $methodReflection->acceptsNamedArguments(),
                            'Method ' . $messagesMethodName . ' invoked with %d parameter, %d required.',
                            'Method ' . $messagesMethodName . ' invoked with %d parameters, %d required.',
                            'Method ' . $messagesMethodName . ' invoked with %d parameter, at least %d required.',
                            'Method ' . $messagesMethodName . ' invoked with %d parameters, at least %d required.',
                            'Method ' . $messagesMethodName . ' invoked with %d parameter, %d-%d required.',
                            'Method ' . $messagesMethodName . ' invoked with %d parameters, %d-%d required.',
                            '%s of method ' . $messagesMethodName . ' expects %s, %s given.',
                            'Result of method ' . $messagesMethodName . ' (void) is used.',
                            '%s of method ' . $messagesMethodName . ' is passed by reference, so it expects variables only.',
                            'Unable to resolve the template type %s in call to method ' . $messagesMethodName,
                            'Missing parameter $%s in call to method ' . $messagesMethodName . '.',
                            'Unknown parameter $%s in call to method ' . $messagesMethodName . '.',
                            'Return type of call to method ' . $messagesMethodName . ' contains unresolvable type.',
                            '%s of method ' . $messagesMethodName . ' contains unresolvable type.',
                            'Method ' . $messagesMethodName . ' invoked with %s, but it\'s not allowed because of @no-named-arguments.',
                        ),
                    ],
                ];
            }
        }

        $errors[] = RuleErrorBuilder::message(sprintf(
            'Neither the property "%1$s" nor one of the methods "%1$s()", "get%1$s()", "is%1$s()", "has%1$s()" or "__call()" exist and have public access in class "%2$s".',
            $propertyOrMethod,
            $objectType->describe(VerbosityLevel::typeOnly()),
        ))
            ->identifier('getAttribute.notFound')
            ->build();

        return [new ErrorType(), $errors];
    }

    /**
     * @return null|array{object: Expr, item: Expr, args: list<Arg>, type: null|Expr}
     */
    public function getNormalizedArguments(StaticCall $methodCall): ?array
    {
        $args = $this->getNamedArguments($methodCall->args);

        if ( ! isset($args[2])) {
            return null;
        }

        if ( ! isset($args[3])) {
            return null;
        }

        if ( ! isset($args['arguments']) && ! isset($args[4])) {
            return null;
        }

        $argsArray = ($args['arguments'] ?? $args[4]);

        if ( ! $argsArray instanceof Expr\Array_) {
            return null;
        }

        $type = ($args['type'] ?? $args[5] ?? null);

        $passedArgs = [];
        foreach ($argsArray->items as $item) {
            $passedArgs[] = new Arg(
                $item->value,
                name: $item->key instanceof String_ ? new Identifier($item->key->value) : null,
            );
        }

        return [
            'object' => $args[2],
            'item' => $args[3],
            'args' => $passedArgs,
            'type' => $type,
        ];
    }

    /**
     * @param array<Arg|VariadicPlaceholder> $args
     *
     * @return array<string|int, Expr>
     */
    private function getNamedArguments(array $args): array
    {
        $argsByName = [];
        foreach ($args as $i => $arg) {
            if ( ! $arg instanceof Arg) {
                continue;
            }

            if ( ! $arg->name instanceof Identifier) {
                $argsByName[$i] = $arg->value;

                continue;
            }

            $argsByName[$arg->name->name] = $arg->value;
        }

        return $argsByName;
    }
}

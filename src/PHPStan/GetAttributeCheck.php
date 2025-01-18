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
use PHPStan\Rules\FileRuleError;
use PHPStan\Rules\FunctionCallParametersCheck;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\LineRuleError;
use PHPStan\Rules\MetadataRuleError;
use PHPStan\Rules\Methods\MethodCallCheck;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Rules\RuleLevelHelper;
use PHPStan\Rules\TipRuleError;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Enum\EnumCaseObjectType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use Twig\Template;

final readonly class GetAttributeCheck
{
    public function __construct(
        private MethodCallCheck $methodCallCheck,
        private FunctionCallParametersCheck $parametersCheck,
        private NonexistentOffsetInArrayDimFetchCheck $nonexistentOffsetInArrayDimFetchCheck,
        private ReflectionProvider $reflectionProvider,
        private RuleLevelHelper $ruleLevelHelper,
        private bool $checkUnionTypes,
        private bool $checkBenevolentUnionTypes,
    ) {}

    /**
     * @return null|array{Type, list<IdentifierRuleError>}
     */
    public function check(StaticCall $node, Scope $scope): ?array
    {
        $arguments = $this->getNormalizedArguments($node);

        if ($arguments === null) {
            return null;
        }

        $objectType = $this->ruleLevelHelper->findTypeToCheck(
            $scope,
            $arguments['object'],
            '',
            static fn(Type $type): bool => true, // To complicate to filter unions, we'll do it manually.
        )->getType();

        if ($objectType instanceof MixedType) {
            return [new MixedType(), []];
        }

        $propertyOrMethodType = $scope->getType($arguments['item']);

        if ($propertyOrMethodType instanceof ConstantIntegerType) {
            $propertyOrMethod = (string) $propertyOrMethodType->getValue();
        } else {
            $constantStringTypes = $propertyOrMethodType->getConstantStrings();

            if (count($constantStringTypes) !== 1) {
                return [new MixedType(), []];
            }

            $propertyOrMethod = $constantStringTypes[0]->getValue();
        }

        $type = Template::ANY_CALL;

        if ($arguments['type'] !== null) {
            $typeStrings = $scope->getType($arguments['type'])->getConstantScalarTypes();

            if (count($typeStrings) !== 1) {
                return [new MixedType(), []];
            }

            $type = (string) $typeStrings[0]->getValue();
        }

        if ($objectType instanceof UnionType) {
            $subTypeResults = [];
            $subTypeErrors = [];
            foreach ($objectType->getTypes() as $subTypes) {
                $result = $this->checkSingleType(
                    $type,
                    $subTypes,
                    $propertyOrMethodType,
                    $propertyOrMethod,
                    $scope,
                    $arguments['args'],
                );

                if ( ! $result[0] instanceof ErrorType) {
                    $subTypeResults[] = $result[0];
                }

                $subTypeErrors[] = $result[1];
            }

            if ($subTypeResults === []) {
                $errors = [
                    RuleErrorBuilder::message(sprintf(
                        'Neither the property "%1$s" nor one of the methods "%1$s()", "get%1$s()", "is%1$s()", "has%1$s()" or "__call()" exist and have public access in class "%2$s".',
                        $propertyOrMethod,
                        $objectType->describe(VerbosityLevel::typeOnly()),
                    ))
                    ->identifier('getAttribute.notFound')
                    ->build(),
                ];
            } elseif (
                ($this->checkUnionTypes && ! $objectType instanceof BenevolentUnionType)
                || ($this->checkBenevolentUnionTypes && $objectType instanceof BenevolentUnionType)
            ) {
                $errorBuilder = RuleErrorBuilder::message(sprintf(
                    'TODO Might not exists',
                ))
                    ->identifier('getAttribute.maybeNotFound');

                $errors = array_merge(...$subTypeErrors);
                foreach ($errors as $error) {
                    $errorBuilder->addTip($error->getMessage());
                }

                $errors = [$errorBuilder->build()];
            } else {
                $errors = [];
            }

            return [
                TypeCombinator::union(...$subTypeResults),
                $errors,
            ];
        }

        return $this->checkSingleType(
            $type,
            $objectType,
            $propertyOrMethodType,
            $propertyOrMethod,
            $scope,
            $arguments['args'],
        );
    }

    /**
     * @param list<Arg> $args
     *
     * @return array{Type, list<IdentifierRuleError>}
     */
    public function checkSingleType(
        string $callType,
        Type $objectType,
        Type $propertyOrMethodType,
        string $propertyOrMethod,
        Scope $scope,
        array $args,
    ): array {
        if ($objectType->isNull()->yes()) {
            $errors = [
                RuleErrorBuilder::message(sprintf(
                    'Cannot get "%s" on null.',
                    $propertyOrMethod,
                ))->identifier('getAttribute.null')->build(),
            ];

            return [new ErrorType(), $errors];
        }

        // vendor/twig/twig/src/Extension/CoreExtension.php:1643
        if ($callType !== Template::METHOD_CALL) {
            if (
                $callType === Template::ARRAY_CALL
                || $objectType->isObject()->no()
                || $objectType->hasOffsetValueType($propertyOrMethodType)->yes() // Handle ArrayObject and ArrayAccess.
            ) {
                return [$objectType->getOffsetValueType($propertyOrMethodType), [
                    // @phpstan-ignore phpstanApi.method
                    ...$this->prefixErrorIdentifier($this->nonexistentOffsetInArrayDimFetchCheck->check(
                        $scope,
                        new TypeExpr($objectType),
                        sprintf('Access to offset %s on an unknown class %%s.', $propertyOrMethodType->describe(VerbosityLevel::value())),
                        $propertyOrMethodType,
                    )),
                ]];
            }

            // object property
            // vendor/twig/twig/src/Extension/CoreExtension.php:1728
            if ($objectType->hasProperty($propertyOrMethod)->yes()) {
                $property = $objectType->getProperty($propertyOrMethod, $scope);

                if ($property->isPublic()) {
                    return [$property->getReadableType(), []];
                }
            }

            if ($objectType->isEnum()->yes()) {
                $className = $objectType->getObjectClassNames()[0];
                $classReflection = $this->reflectionProvider->getClass($className);

                if ($classReflection->hasEnumCase($propertyOrMethod)) {
                    return [
                        new EnumCaseObjectType($className, $propertyOrMethod, $classReflection),
                        [],
                    ];
                }
            }

            if ($objectType->hasConstant($propertyOrMethod)->yes()) {
                return [
                    $scope->getType($objectType->getConstant($propertyOrMethod)->getValueExpr()),
                    [],
                ];
            }
        }

        if (in_array($callType, [Template::ANY_CALL, Template::METHOD_CALL], true)) {
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
                    $args,
                    $methodReflection->getVariants(),
                    $methodReflection->getNamedArgumentsVariants(),
                );

                return [
                    $parametersAcceptor->getReturnType(),
                    [
                        // @phpstan-ignore phpstanApi.method
                        ...$this->prefixErrorIdentifier($this->parametersCheck->check(
                            $parametersAcceptor,
                            $scope,
                            $declaringClass->isBuiltin(),
                            new Expr\MethodCall(
                                new TypeExpr($objectType),
                                new Identifier($methodReflection->getName()),
                                $args,
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
                        )),
                    ],
                ];
            }
        }

        $errors = [
            RuleErrorBuilder::message(sprintf(
                'Neither the property "%1$s" nor one of the methods "%1$s()", "get%1$s()", "is%1$s()", "has%1$s()" or "__call()" exist and have public access in class "%2$s".',
                $propertyOrMethod,
                $objectType->describe(VerbosityLevel::typeOnly()),
            ))
            ->identifier('getAttribute.notFound')
            ->build(),
        ];

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

    /**
     * @param array<IdentifierRuleError> $errors
     *
     * @return list<IdentifierRuleError>
     */
    private function prefixErrorIdentifier(array $errors): array
    {
        $errorFormatted = [];
        foreach ($errors as $error) {
            $errorBuilder = RuleErrorBuilder::message($error->getMessage())
                ->identifier('getAttribute.' . $error->getIdentifier());

            if ($error instanceof LineRuleError) {
                $errorBuilder->line($error->getLine());
            }

            if ($error instanceof FileRuleError) {
                $errorBuilder->file($error->getFile(), $error->getFileDescription());
            }

            if ($error instanceof TipRuleError) {
                $errorBuilder->tip($error->getTip());
            }

            if ($error instanceof MetadataRuleError) {
                $errorBuilder->metadata($error->getMetadata());
            }

            $errorFormatted[] = $errorBuilder->build();
        }

        return $errorFormatted;
    }
}

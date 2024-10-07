<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\DynamicReturnType;

use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\FunctionParameterOutTypeExtension;
use PHPStan\Type\Type;

final readonly class TypeHintReturnType implements FunctionParameterOutTypeExtension
{
    public function __construct(
        private PhpDocParser $phpDocParser,
        private Lexer $lexer,
        private TypeNodeResolver $typeNodeResolver,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection, ParameterReflection $parameter): bool
    {
        return $functionReflection->getName() === 'twigstan_type_hint' && $parameter->getName() === 'context';
    }

    public function getParameterOutTypeFromFunctionCall(FunctionReflection $functionReflection, FuncCall $funcCall, ParameterReflection $parameter, Scope $scope): Type
    {
        if ( ! $funcCall->getArgs()[0]->value instanceof Variable) {
            return new ErrorType();
        }

        if ( ! $funcCall->getArgs()[1]->value instanceof String_) {
            return new ErrorType();
        }

        if ( ! $funcCall->getArgs()[2]->value instanceof String_) {
            return new ErrorType();
        }

        if ( ! $funcCall->getArgs()[3]->value instanceof ConstFetch) {
            return new ErrorType();
        }

        $context = $scope->getType($funcCall->getArgs()[0]->value);

        // @phpstan-ignore phpstanApi.instanceofType
        if ( ! $context instanceof ConstantArrayType) {
            return new ErrorType();
        }

        $name = $funcCall->getArgs()[1]->value->value;
        $typeString = $funcCall->getArgs()[2]->value->value;
        $optional = $scope->getType($funcCall->getArgs()[3]->value);

        $value = $this->phpDocParser->parseTagValue(
            new TokenIterator($this->lexer->tokenize($typeString)),
            '@var',
        );

        if ( ! $value instanceof VarTagValueNode) {
            return new ErrorType();
        }

        $type = $this->typeNodeResolver->resolve($value->type, new NameScope(null, []));

        $builder = ConstantArrayTypeBuilder::createFromConstantArray($context);
        $builder->setOffsetValueType(new ConstantStringType($name), $type, $optional->isTrue()->yes());

        return $builder->getArray();
    }
}

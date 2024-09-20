<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\TypeSpecifying;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Type;

final readonly class VarTypeSpecifyingExtension implements DynamicFunctionReturnTypeExtension
{
    public function __construct(
        private PhpDocParser $phpDocParser,
        private Lexer $lexer,
        private TypeNodeResolver $typeNodeResolver,
    ) {}

    public function isFunctionSupported(FunctionReflection $functionReflection): bool
    {
        return $functionReflection->getName() === 'twigstan_type_hint';
    }

    public function getTypeFromFunctionCall(
        FunctionReflection $functionReflection,
        FuncCall $functionCall,
        Scope $scope,
    ): ?Type {
        if (!$functionCall->getArgs()[0]->value instanceof String_) {
            return new ErrorType();
        }

        $type = $functionCall->getArgs()[0]->value->value;

        $value = $this->phpDocParser->parseTagValue(
            new TokenIterator($this->lexer->tokenize($type)),
            '@var',
        );

        if (!$value instanceof VarTagValueNode) {
            return new ErrorType();
        }

        return $this->typeNodeResolver->resolve($value->type, new NameScope(null, []));
    }
}

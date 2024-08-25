<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\TypeSpecifying;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\NameScope;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Type\FunctionTypeSpecifyingExtension;

final readonly class VarTypeSpecifyingExtension implements FunctionTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function __construct(
        private PhpDocParser $phpDocParser,
        private Lexer $lexer,
        private TypeNodeResolver $typeNodeResolver,
    ) {}

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }

    public function isFunctionSupported(FunctionReflection $functionReflection, FuncCall $node, TypeSpecifierContext $context): bool
    {
        if ($node->name->toString() !== 'twigstan_type_hint') {
            return false;
        }

        if (count($node->getArgs()) !== 2) {
            return false;
        }

        if (!$node->getArgs()[1]->value instanceof String_) {
            return false;
        }

        if (!$node->getArgs()[1]->value instanceof String_) {
            return false;
        }

        return true;
    }

    public function specifyTypes(FunctionReflection $functionReflection, FuncCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
    {
        if (!$node->getArgs()[0]->value instanceof String_) {
            return new SpecifiedTypes();
        }

        if (!$node->getArgs()[1]->value instanceof String_) {
            return new SpecifiedTypes();
        }

        $name = $node->getArgs()[0]->value->value;
        $type = $node->getArgs()[1]->value->value;

        $value = $this->phpDocParser->parseTagValue(
            new TokenIterator($this->lexer->tokenize($type)),
            '@var',
        );

        if (! $value instanceof VarTagValueNode) {
            return new SpecifiedTypes();
        }

        return $this->typeSpecifier->create(
            new Variable($name),
            $this->typeNodeResolver->resolve($value->type, new NameScope(null, [])),
            TypeSpecifierContext::createTruthy(),
        );
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\PHP;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Type;

final readonly class PhpDocToPhpStanTypeResolver
{
    private Lexer $lexer;
    private TypeParser $typeParser;

    public function __construct(private TypeNodeResolver $typeNodeResolver)
    {
        $this->lexer = new Lexer();
        $this->typeParser = new TypeParser(new ConstExprParser(true, true), true, );
    }

    /**
     * @param array<string, string> $phpDocTypes
     */
    public function resolveArray(array $phpDocTypes): ConstantArrayType
    {
        $keyTypes = [];
        $valueTypes = [];
        $optionalKeys = [];
        $keyId = 0;
        foreach ($phpDocTypes as $key => $phpDocType) {
            if (str_ends_with($key, '?')) {
                $key = substr($key, 0, -1);
                $optionalKeys[] = $keyId;
            }
            $keyTypes[$keyId] = new ConstantStringType($key);
            $valueTypes[$keyId] = $this->resolve($phpDocType);
            $keyId++;
        }

        return new ConstantArrayType($keyTypes, $valueTypes, optionalKeys: $optionalKeys);
    }

    public function resolve(string $phpDocType): Type
    {
        return $this->typeNodeResolver->resolve(
            $this->typeParser->parse(new TokenIterator($this->lexer->tokenize($phpDocType))),
            new NameScope(null, []),
        );
    }
}

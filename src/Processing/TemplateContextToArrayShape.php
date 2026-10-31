<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeUnsealedTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use TwigStan\Processing\ScopeInjection\ArrayShapeMerger;

final readonly class TemplateContextToArrayShape
{
    public function __construct(
        private ArrayShapeMerger $arrayShapeMerger,
        private PhpDocParser $phpDocParser,
        private Lexer $lexer,
    ) {}

    public function getByTemplate(TemplateContext $templateContext, string $template): ArrayShapeNode
    {
        $newContext = null;
        foreach ($templateContext->getUniqueContextByTemplate($template) as $context) {
            $contextShape = $this->parse($context);

            if ($contextShape === null) {
                continue;
            }

            if ($newContext === null) {
                $newContext = $contextShape;

                continue;
            }

            $newContext = $this->arrayShapeMerger->merge(
                $newContext,
                $contextShape,
            );
        }

        return $newContext ?? ArrayShapeNode::createSealed([]);
    }

    /**
     * @return array<string, list<ArrayShapeNode>> The arguments of each call to each macro of the template
     */
    public function getByMacro(TemplateContext $templateContext, string $template): array
    {
        $macros = [];
        foreach ($templateContext->getUniqueContextByMacro($template) as $macro => $contexts) {
            $macros[$macro] = array_values(array_filter(array_map($this->parse(...), $contexts)));
        }

        return $macros;
    }

    private function parse(string $context): ?ArrayShapeNode
    {
        if ($context === 'array{}') {
            return ArrayShapeNode::createSealed([]);
        }

        $phpDocNode = $this->phpDocParser->parseTagValue(
            new TokenIterator($this->lexer->tokenize($context)),
            '@var',
        );

        if ( ! $phpDocNode instanceof VarTagValueNode) {
            return null;
        }

        // A context without a shape, such as array<string, mixed>, can hold any variable.
        if ( ! $phpDocNode->type instanceof ArrayShapeNode) {
            return ArrayShapeNode::createUnsealed(
                [],
                new ArrayShapeUnsealedTypeNode(new IdentifierTypeNode('mixed'), new IdentifierTypeNode('string')),
            );
        }

        return $phpDocNode->type;
    }
}

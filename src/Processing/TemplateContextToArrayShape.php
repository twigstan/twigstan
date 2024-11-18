<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
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
            $contextShape = ArrayShapeNode::createSealed([]);

            if ($context !== 'array{}') {
                $phpDocNode = $this->phpDocParser->parseTagValue(
                    new TokenIterator($this->lexer->tokenize($context)),
                    '@var',
                );

                if ( ! $phpDocNode instanceof VarTagValueNode) {
                    continue;
                }

                $contextShape = $phpDocNode->type;

                if ( ! $contextShape instanceof ArrayShapeNode) {
                    $contextShape = ArrayShapeNode::createSealed([]);
                }
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
}

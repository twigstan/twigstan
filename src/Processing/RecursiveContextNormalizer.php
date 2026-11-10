<?php

declare(strict_types=1);

namespace TwigStan\Processing;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Printer\Printer;
use TwigStan\Processing\ScopeInjection\ArrayShapeMerger;

final readonly class RecursiveContextNormalizer
{
    public function __construct(
        private PhpDocParser $parser,
        private Lexer $lexer,
        private ArrayShapeMerger $arrayShapeMerger,
    ) {}

    public function normalize(TemplateContext $incoming, TemplateContext $known): TemplateContext
    {
        $edges = [];
        foreach ([$known, $incoming] as $context) {
            foreach ($context->context as $template => $renderPoints) {
                if (str_contains($template, '::')) {
                    continue;
                }

                foreach ($renderPoints as [$location]) {
                    $edges[$location->last()->fileName][$template] = true;
                }
            }
        }

        $contexts = $incoming->context;
        foreach ($contexts as $template => $renderPoints) {
            if (str_contains($template, '::')) {
                continue;
            }

            foreach ($renderPoints as $hash => [$location, $context]) {
                if ( ! $this->reaches($template, $location->last()->fileName, $edges)) {
                    continue;
                }

                $node = $this->parser->parseTagValue(new TokenIterator($this->lexer->tokenize($context)), '@var');

                if ($node instanceof VarTagValueNode) {
                    $contexts[$template][$hash] = [$location, (new Printer())->print($this->boundAncestry($node->type))];
                }
            }
        }

        return new TemplateContext($contexts);
    }

    /**
     * @param array<string, array<string, true>> $edges
     * @param array<string, true> $visited
     */
    private function reaches(string $from, string $target, array $edges, array &$visited = []): bool
    {
        if ($from === $target) {
            return true;
        }

        $visited[$from] = true;
        foreach ($edges[$from] ?? [] as $next => $_) {
            if ( ! isset($visited[$next]) && $this->reaches($next, $target, $edges, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function boundAncestry(TypeNode $type, bool $ancestor = false, bool $loop = false): TypeNode
    {
        if ($type instanceof UnionTypeNode || $type instanceof IntersectionTypeNode) {
            $members = $type->types;

            if ($type instanceof UnionTypeNode && ($ancestor || $loop)) {
                // Recursive ancestry needs field unions, rather than an expanding set of correlated snapshots.
                $shape = null;
                $members = [];
                foreach ($type->types as $member) {
                    if ($member instanceof ArrayShapeNode) {
                        $shape = $shape === null ? $member : $this->arrayShapeMerger->merge($shape, $member);
                    } else {
                        $members[] = $member;
                    }
                }

                if ($shape !== null) {
                    $members[] = $shape;
                }
            }

            $members = array_map(fn(TypeNode $member): TypeNode => $this->boundAncestry($member, $ancestor, $loop), $members);

            if (count($members) === 1) {
                return $members[0];
            }

            $result = clone $type;
            $result->types = $members;

            return $result;
        }

        if ( ! $type instanceof ArrayShapeNode) {
            return $type;
        }

        $result = clone $type;
        $result->items = [];
        foreach ($type->items as $item) {
            $key = match (true) {
                $item->keyName instanceof ConstExprStringNode => $item->keyName->value,
                $item->keyName instanceof IdentifierTypeNode => $item->keyName->name,
                default => null,
            };
            $value = $item->valueType;

            if ($loop ? $key === 'parent' : $key === '_parent') {
                // A recursive include keeps one typed parent, but cannot accumulate infinite parent snapshots.
                $value = $ancestor
                    ? new GenericTypeNode(new IdentifierTypeNode('array'), [new IdentifierTypeNode('mixed')])
                    : $this->boundAncestry($value, true);
            } elseif ( ! $loop && $key === 'loop') {
                $value = $this->boundAncestry($value, $ancestor, true);
            }

            $result->items[] = new ArrayShapeItemNode($item->keyName, $item->optional, $value);
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection;

use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

final readonly class ArrayShapeMerger
{
    /**
     * Walks through both array shapes. If a key exists in both, they are added.
     * If a key exists in only one, it is added as optional.
     * A new array shape will be returned containing the shapes of both.
     * It will not use recursive.
     */
    public function merge(ArrayShapeNode $left, ArrayShapeNode $right, bool $append = false): ArrayShapeNode
    {
        $leftItemsByKey = [];
        foreach ($left->items as $item) {
            $leftItemsByKey[(string) $item->keyName] = $item;
        }

        $rightItemsByKey = [];
        foreach ($right->items as $item) {
            $rightItemsByKey[(string) $item->keyName] = $item;
        }

        $items = [];
        foreach (array_unique(array_merge(array_keys($leftItemsByKey), array_keys($rightItemsByKey))) as $key) {
            $leftItem = $leftItemsByKey[$key] ?? null;
            $rightItem = $rightItemsByKey[$key] ?? null;

            if ($leftItem === null && $rightItem === null) {
                continue;
            }

            if ($leftItem === null) {
                $items[] = new ArrayShapeItemNode(
                    $rightItem->keyName,
                    $append ? $rightItem->optional : true,
                    $rightItem->valueType,
                );

                continue;
            }

            if ($rightItem === null) {
                $items[] = new ArrayShapeItemNode(
                    $leftItem->keyName,
                    $append ? $leftItem->optional : true,
                    $leftItem->valueType,
                );

                continue;
            }

            $valueType = $this->union($leftItem->valueType, $rightItem->valueType);

            $items[] = new ArrayShapeItemNode(
                $leftItem->keyName,
                $leftItem->optional || $rightItem->optional,
                $valueType,
            );
        }

        return ArrayShapeNode::createSealed($items);
    }

    /**
     * Each type is added once, otherwise the types of contexts merged from many render points grow with every one of them.
     */
    private function union(TypeNode $left, TypeNode $right): TypeNode
    {
        $types = [];
        foreach ([$left, $right] as $type) {
            foreach ($type instanceof UnionTypeNode ? $type->types : [$type] as $member) {
                $types[(string) $member] ??= $member;
            }
        }

        return count($types) === 1 ? reset($types) : new UnionTypeNode(array_values($types));
    }
}

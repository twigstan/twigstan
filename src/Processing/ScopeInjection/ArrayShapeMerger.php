<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection;

use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
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

            if ((string) $leftItem->valueType === (string) $rightItem->valueType) {
                $valueType = $leftItem->valueType;
            } else {
                if ($leftItem->valueType instanceof UnionTypeNode && $rightItem->valueType instanceof UnionTypeNode) {
                    $valueType = new UnionTypeNode([...$leftItem->valueType->types, ...$rightItem->valueType->types]);
                } elseif ($leftItem->valueType instanceof UnionTypeNode) {
                    $valueType = new UnionTypeNode([...$leftItem->valueType->types, $rightItem->valueType]);
                } elseif ($rightItem->valueType instanceof UnionTypeNode) {
                    $valueType = new UnionTypeNode([$leftItem->valueType, ...$rightItem->valueType->types]);
                } else {
                    $valueType = new UnionTypeNode([$leftItem->valueType, $rightItem->valueType]);
                }
            }

            $items[] = new ArrayShapeItemNode(
                $leftItem->keyName,
                $leftItem->optional || $rightItem->optional,
                $valueType,
            );
        }

        return new ArrayShapeNode($items);
    }
}

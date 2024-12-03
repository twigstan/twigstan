<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Accessory\HasOffsetValueType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\IntersectionType;

/**
 * @implements Collector<MethodReturnStatementsNode, array{
 *     macros: string,
 * }>
 */
final readonly class MacroCollector implements Collector, ExportingCollector
{
    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if ($node->getMethodName() !== 'doDisplay') {
            return null;
        }

        $returnScope = $node->getStatementResult()->getScope();

        if ($returnScope->hasVariableType('macros')->no()) {
            return null;
        }

        $macros = $returnScope->getVariableType('macros');

        // @phpstan-ignore phpstanApi.instanceofType
        if ( ! $macros instanceof IntersectionType) {
            return null;
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($macros->getTypes() as $type) {
            // @phpstan-ignore phpstanApi.class
            if ( ! $type instanceof HasOffsetValueType) {
                continue;
            }

            // @phpstan-ignore phpstanApi.method, phpstanApi.method
            $builder->setOffsetValueType($type->getOffsetType(), $type->getValueType());
        }

        return [
            'macros' => (new Printer())->print($builder->getArray()->toPhpDocNode()),
        ];
    }
}

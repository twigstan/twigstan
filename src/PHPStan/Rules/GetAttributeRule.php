<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use Twig\Extension\CoreExtension;
use TwigStan\PHPStan\GetAttributeCheck;
use TwigStan\PHPStan\Visitor\AssertTypeVisitor;

/**
 * @implements Rule<Node\Expr\StaticCall>
 */
final readonly class GetAttributeRule implements Rule
{
    public function __construct(private GetAttributeCheck $attributeCheck) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ( ! $node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->name !== 'getAttribute') {
            return [];
        }

        if ( ! $node->class instanceof Node\Name\FullyQualified) {
            return [];
        }

        if ($node->class->toString() !== CoreExtension::class) {
            return [];
        }

        if ($node->getAttribute(AssertTypeVisitor::ATTRIBUTE_NAME) === true) {
            return [];
        }

        $result = $this->attributeCheck->check($node, $scope);

        if ($result === null) {
            return [];
        }

        return $result[1];
    }
}

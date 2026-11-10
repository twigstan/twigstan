<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Type\ErrorType;
use Twig\Extension\CoreExtension;
use TwigStan\PHPStan\GetAttributeCheck;
use TwigStan\PHPStan\Visitor\AssertTypeVisitor;

/**
 * @implements Rule<Node\Expr\StaticCall>
 */
#[Conditional(attribute: new AutowiredService(), if: '%twigstan.phases.analysis%')]
final readonly class GetAttributeRule implements Rule
{
    private const array MAYBE_NOT_FOUND = ['getAttribute.offsetAccess.notFound', 'getAttribute.maybeNotFound'];

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

        // The defined test checks whether the attribute exists, and the reads that it guards have a specified type,
        // see DefinedTestTypeSpecifyingExtension. An attribute that cannot exist is still reported.
        if ($this->isDefinedTest($node) ? ! $result[0] instanceof ErrorType : $scope->hasExpressionType($node)->yes()) {
            return array_values(array_filter(
                $result[1],
                fn(IdentifierRuleError $error) => ! in_array($error->getIdentifier(), self::MAYBE_NOT_FOUND, true),
            ));
        }

        return $result[1];
    }

    private function isDefinedTest(StaticCall $node): bool
    {
        $isDefinedTest = $node->getArgs()[6]->value ?? null;

        return $isDefinedTest instanceof Node\Expr\ConstFetch && $isDefinedTest->name->toLowerString() === 'true';
    }
}

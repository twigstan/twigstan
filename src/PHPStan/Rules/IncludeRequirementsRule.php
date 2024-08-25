<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use Twig\Error\LoaderError;
use TwigStan\PHP\ExpressionToStringResolver;
use TwigStan\PHPStan\RequirementsVerifier;
use TwigStan\PHPStan\Type\RequirementsConstantArrayType;
use TwigStan\Twig\Requirements\RequirementsNotFoundException;
use TwigStan\Twig\Requirements\RequirementsReader;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
class IncludeRequirementsRule implements Rule
{
    public function __construct(
        private RequirementsReader $requirementsReader,
        private ExpressionToStringResolver $expressionToStringResolver,
    ) {}

    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof Node\Expr\FuncCall) {
            return [];
        }

        if (! $node->name instanceof Node\Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'twigstan_include') {
            return [];
        }

        try {
            $result = $this->expressionToStringResolver->resolve($node->args[0]->value, $scope);
        } catch (ShouldNotHappenException) {
            return [];
        }

        $errors = $result->errors;

        $context = $scope->getType($node->args[1]->value);
        $variables = $scope->getType($node->args[2]->value);
        $only = $node->args[3]->value->name->toLowerString() === 'true';

        foreach ($result->values as $template) {
            try {
                $requirements = $this->requirementsReader->read($template);
            } catch (RequirementsNotFoundException) {
                continue;
            } catch (LoaderError) {
                $errors[] = RuleErrorBuilder::message(sprintf('Template "%s" not found.', $template))
                    ->line($node->getLine())
                    ->identifier('twig.include.templateNotFound')
                    ->build();

                continue;
            }

            if (!$only) {
                $variables = $this->enrichVariablesFromContext($requirements, $context, $variables);
            }

            $accepts = $requirements->acceptsWithReason($variables, true, true, true);

            foreach ($accepts->reasons as $reason) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Requirements for template "%s" are not valid: %s.',
                    $template,
                    rtrim($reason, '.'),
                ))->identifier('twig.include.invalidRequirements')->line($node->getLine())->build();
            }
        }

        return $errors;
    }

    private function enrichVariablesFromContext(
        RequirementsConstantArrayType $requirements,
        ConstantArrayType $context,
        ConstantArrayType $variables,
    ): ConstantArrayType {
        $contextIntersect = $context->intersectKeyArray($requirements);

        $newArrayBuilder = ConstantArrayTypeBuilder::createEmpty();
        foreach ([$contextIntersect, $variables] as $argType) {
            if (!$argType instanceof ConstantArrayType) {
                throw new ShouldNotHappenException();
            }

            $keyTypes = $argType->getKeyTypes();
            $valueTypes = $argType->getValueTypes();
            $optionalKeys = $argType->getOptionalKeys();

            foreach ($keyTypes as $k => $keyType) {
                $isOptional = in_array($k, $optionalKeys, true);

                $newArrayBuilder->setOffsetValueType(
                    $keyType instanceof ConstantIntegerType ? null : $keyType,
                    $valueTypes[$k],
                    $isOptional,
                );
            }
        }

        return $newArrayBuilder->getArray();
    }
}

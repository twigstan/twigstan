<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Twig\Error\LoaderError;
use TwigStan\PHP\ExpressionToStringResolver;
use TwigStan\Twig\Requirements\RequirementsNotFoundException;
use TwigStan\Twig\Requirements\RequirementsReader;

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
final class ExtendsRequirementsRule implements Rule
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

        if ($node->name->toLowerString() !== 'twigstan_extends') {
            return [];
        }

        $result = $this->expressionToStringResolver->resolve($node->args[0]->value, $scope);

        $errors = $result->errors;

        if ($result->values === []) {
            return $errors;
        }

        $context = $scope->getType($node->args[1]->value);

        foreach ($result->values as $template) {
            try {
                $requirements = $this->requirementsReader->read($template);
            } catch (RequirementsNotFoundException) {
                continue;
            } catch (LoaderError) {
                $errors[] = RuleErrorBuilder::message(sprintf('Template "%s" not found.', $template))
                    ->line($node->getLine())
                    ->identifier('twig.extends.templateNotFound')
                    ->build();

                continue;
            }

            if (!$context->isConstantArray()->yes()) {
                $errors[] = RuleErrorBuilder::message('Context is not a constant array.')
                    ->line($node->getLine())
                    ->identifier('twig.extends.invalidContext')
                    ->build();

                continue;
            }

            $accepts = $requirements->acceptsWithReason($context, true);

            foreach ($accepts->reasons as $reason) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Requirements for template "%s" are not valid: %s.',
                    $template,
                    rtrim($reason, '.'),
                ))->identifier('twig.extends.invalidRequirements')->line($node->getLine())->build();
            }
        }

        return $errors;
    }


}

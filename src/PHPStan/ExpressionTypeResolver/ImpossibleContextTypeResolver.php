<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\ExpressionTypeResolver;

use PhpParser\Node\Expr;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;

/**
 * In `{% if deadline is defined %}`, the context of a render point that does not pass deadline is never, because
 * the branch is not rendered for it. PHPStan still analyses the branch, since TwigStan does not treat PHPDoc types
 * as certain, and would read the variables of that context as an error type. They are never instead, which is accepted everywhere.
 */
#[Conditional(attribute: new AutowiredService(), if: '%twigstan.phases.analysis%')]
final readonly class ImpossibleContextTypeResolver implements ExpressionTypeResolverExtension
{
    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if (
            ! $expr instanceof Expr\ArrayDimFetch
            || ! $expr->var instanceof Expr\Variable
            || $expr->var->name !== 'context'
            || ! $scope->getType($expr->var) instanceof NeverType
        ) {
            return null;
        }

        return new NeverType(true);
    }
}

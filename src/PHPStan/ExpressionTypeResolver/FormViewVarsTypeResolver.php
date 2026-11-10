<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\ExpressionTypeResolver;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\DependencyInjection\Conditional;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use Symfony\Component\Form\FormView;

/**
 * Types the variables that every form view has, see https://symfony.com/doc/current/form/form_customization.html#form-variables-reference.
 * FormView::$vars is declared as array, so form.vars.id would be mixed.
 *
 * The other variables, such as choices or the ones that form type extensions add, stay mixed: an array shape
 * would report them as offsets that might not exist.
 */
#[Conditional(attribute: new AutowiredService(), if: 'not(%twigstan.phases.templateContextCollecting%)')]
final class FormViewVarsTypeResolver implements ExpressionTypeResolverExtension
{
    /**
     * Set by BaseType and FormType. PHPStan generalizes an array with more than 16 known offsets, so only those that
     * templates read most are listed. The others, such as value, are mixed anyway.
     */
    private const array VARS = [
        'id' => 'string',
        'name' => 'string',
        'full_name' => 'string',
        'label' => 'string|\Symfony\Contracts\Translation\TranslatableInterface|false|null',
        'help' => 'string|\Symfony\Contracts\Translation\TranslatableInterface|null',
        'attr' => 'array<string, scalar|\Stringable|null>',
        'row_attr' => 'array<string, scalar|\Stringable|null>',
        'label_attr' => 'array<string, scalar|\Stringable|null>',
        'help_attr' => 'array<string, scalar|\Stringable|null>',
        'block_prefixes' => 'list<string>',
        'translation_domain' => 'string|false|null',
        'errors' => '\Symfony\Component\Form\FormErrorIterator<\Symfony\Component\Form\FormError>',
        'required' => 'bool',
        'disabled' => 'bool',
        'compound' => 'bool',
        'valid' => 'bool',
    ];

    private ?Type $varsType = null;

    public function __construct(
        private readonly TypeStringResolver $typeStringResolver,
    ) {}

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if (
            ! $expr instanceof Expr\PropertyFetch
            || ! $expr->name instanceof Identifier
            || $expr->name->name !== 'vars'
            || ! (new ObjectType(FormView::class))->isSuperTypeOf($scope->getType($expr->var))->yes()
        ) {
            return null;
        }

        if ($this->varsType === null) {
            // An array<string, mixed> with these offsets, as PHPStan types it after assigning them.
            $this->varsType = new ArrayType(new StringType(), new MixedType());
            foreach (self::VARS as $name => $type) {
                $this->varsType = $this->varsType->setOffsetValueType(new ConstantStringType($name), $this->typeStringResolver->resolve($type));
            }
        }

        return $this->varsType;
    }
}

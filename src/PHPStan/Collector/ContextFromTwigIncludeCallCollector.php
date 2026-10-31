<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use LogicException;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDocParser\Printer\Printer;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;
use Symfony\Bridge\Twig\AppVariable;
use Twig\Extension\CoreExtension;
use TwigStan\Twig\CommentHelper;

/**
 * @implements TemplateContextCollector<Node\Stmt\Expression>
 */
final readonly class ContextFromTwigIncludeCallCollector implements TemplateContextCollector
{
    public function getNodeType(): string
    {
        return Node\Stmt\Expression::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        // Find: yield \Twig\Extension\CoreExtension::include($this->env, $context, "@EndToEnd/Include/footer.twig", ["title" => "Hello, World!"], \false);

        if ( ! $node->expr instanceof Node\Expr\Yield_) {
            return null;
        }

        if ( ! $node->expr->value instanceof Node\Expr\StaticCall) {
            return null;
        }

        if ( ! $node->expr->value->class instanceof Node\Name\FullyQualified) {
            return null;
        }

        if ($node->expr->value->class->toString() !== CoreExtension::class) {
            return null;
        }

        if ( ! $node->expr->value->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->expr->value->name->name !== 'include') {
            return null;
        }

        $sourceLocation = null;
        foreach ($node->getComments() as $comment) {
            $sourceLocation = CommentHelper::getSourceLocationFromComment($comment->getText());

            if ($sourceLocation !== null) {
                break;
            }
        }

        if ($sourceLocation === null) {
            throw new LogicException(sprintf('Could not find Twig line number on %s:%d.', $scope->getFile(), $node->getStartLine()));
        }

        $args = $node->expr->value->getArgs();

        if (count($args) < 3) {
            return null;
        }

        $contexts = $scope->getType($args[1]->value)->getConstantArrays();

        if (count($contexts) !== 1) {
            return null;
        }

        $context = $contexts[0];

        $templates = $scope->getType($args[2]->value)->getConstantStrings();

        if (count($templates) === 0) {
            return null;
        }

        $variables = isset($args[3]) ? $scope->getType($args[3]->value)->getConstantArrays() : [new ConstantArrayType([], [])];

        if (count($variables) !== 1) {
            return null;
        }

        $variables = $variables[0];

        $withContext = ! isset($args[4]) || $scope->getType($args[4]->value)->isTrue()->yes();

        if ($withContext) {
            $builder = ConstantArrayTypeBuilder::createFromConstantArray($context);

            $app = $this->getAppWithNarrowedUser($context, $scope);

            if ($app !== null) {
                $builder->setOffsetValueType(new ConstantStringType('app'), $app);
            }

            foreach ($variables->getKeyTypes() as $key) {
                $builder->setOffsetValueType($key, $variables->getOffsetValueType($key));
            }

            $templateContext = $builder->getArray();
        } else {
            $templateContext = $variables;
        }

        $result = [];
        foreach ($templates as $template) {
            $result[] = [
                'sourceLocation' => $sourceLocation,
                'template' => $template->getValue(),
                'context' => (new Printer())->print($templateContext->toPhpDocNode()),
            ];
        }

        return $result;
    }

    /**
     * Keeps app.user not null in templates that are only included when there is a user, such as in {% if app.user %}.
     */
    private function getAppWithNarrowedUser(ConstantArrayType $context, Scope $scope): ?Type
    {
        $app = new ConstantStringType('app');

        if ( ! $context->hasOffsetValueType($app)->yes()) {
            return null;
        }

        $declaredUser = $context->getOffsetValueType($app)->getTemplateType(AppVariable::class, 'TUser');

        if ($declaredUser instanceof ErrorType || $declaredUser->isNull()->no()) {
            return null;
        }

        // How TwigStan compiles app.user, printed the same way as PHPStan remembers the type narrowed by {% if app.user %}
        $string = static fn(string $value) => new Node\Scalar\String_($value, ['kind' => Node\Scalar\String_::KIND_DOUBLE_QUOTED]);
        $false = new Node\Arg(new Node\Expr\ConstFetch(new Node\Name\FullyQualified('false')));
        $user = $scope->getType(new Node\Expr\StaticCall(
            new Node\Name\FullyQualified(CoreExtension::class),
            'getAttribute',
            [
                new Node\Arg(new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), 'env')),
                new Node\Arg(new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), 'source')),
                new Node\Arg(new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('context'), new Node\Scalar\String_('app'))),
                new Node\Arg($string('user')),
                new Node\Arg(new Node\Expr\Array_([], ['kind' => Node\Expr\Array_::KIND_SHORT])),
                new Node\Arg($string('any')),
                $false,
                $false,
                $false,
                new Node\Arg(new Node\Scalar\Int_(0)),
            ],
        ));

        if ( ! $user->isNull()->no() || ! $declaredUser->isSuperTypeOf($user)->yes()) {
            return null;
        }

        return new GenericObjectType(AppVariable::class, [$user]);
    }
}

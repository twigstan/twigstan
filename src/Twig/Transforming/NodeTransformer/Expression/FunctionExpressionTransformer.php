<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer\Expression;

use Closure;
use PhpParser\Node as PhpNode;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\ShouldNotHappenException;
use ReflectionFunction;
use Twig\Environment;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\Node;
use Twig\TwigFilter;
use Twig\TwigFunction;
use TwigStan\Twig\Node\NodeMapper;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;
use TwigStan\Twig\Transforming\NodeTransformer\TwigNodeTransformer;
use TwigStan\Twig\Transforming\TransformScope;

/**
 * @implements TwigNodeTransformer<FunctionExpression>
 */
final readonly class FunctionExpressionTransformer implements TwigNodeTransformer
{
    public function __construct(
        private Environment $twig,
        private NodeMapper $nodeMapper,
    ) {}

    public static function getType(): array
    {
        return [FunctionExpression::class, FilterExpression::class, TestExpression::class];
    }

    public function transform(Node $node, TransformScope $scope, DelegatingTwigNodeTransformer $delegator): PhpNode
    {
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'constant') {
            $constant = $delegator->transform($node->getNode('arguments')->getNode('0'), $scope)->value;

            if (str_contains($constant, '::')) {
                [$class, $constant] = explode('::', $constant);

                return new ClassConstFetch(
                    new Name\FullyQualified(ltrim($class, '\\', )),
                    $constant,
                );
            }

            return new ConstFetch(
                new Name($delegator->transform($node->getNode('arguments')->getNode('0'), $scope)->value),
            );
        }

        if ($node instanceof FunctionExpression || $node instanceof FilterExpression || $node instanceof TestExpression) {
            $twigFunctionOrFilterOrTest = match(true) {
                $node instanceof FilterExpression => $this->twig->getFilter($node->getNode('filter')->getAttribute('value')),
                $node instanceof FunctionExpression => $this->twig->getFunction($node->getAttribute('name')),
                $node instanceof TestExpression => $this->twig->getTest($node->getAttribute('name')),
            };

            $args = [];

            if (($twigFunctionOrFilterOrTest instanceof TwigFilter || $twigFunctionOrFilterOrTest instanceof TwigFunction) && $twigFunctionOrFilterOrTest->needsCharset()) {
                $args[] = new Arg(
                    new String_($this->twig->getCharset()),
                );
            }

            if (($twigFunctionOrFilterOrTest instanceof TwigFilter || $twigFunctionOrFilterOrTest instanceof TwigFunction) && $twigFunctionOrFilterOrTest->needsEnvironment()) {
                $args[] = new Arg(
                    new FuncCall(
                        new Name('twigstan_get_object'),
                        [new Arg(new ClassConstFetch(new Name\FullyQualified(Environment::class), 'class'))],
                    ),
                );
            }

            if (($twigFunctionOrFilterOrTest instanceof TwigFilter || $twigFunctionOrFilterOrTest instanceof TwigFunction) && $twigFunctionOrFilterOrTest->needsContext()) {
                $args[] = new Arg(
                    new Variable($scope->getContextVariable()),
                );
            }

            if ($node instanceof FilterExpression) {
                $args[] = new Arg(
                    $delegator->transform($node->getNode('node'), $scope),
                );
            }

            if ($node instanceof TestExpression) {
                $args[] = new Arg(
                    $delegator->transform($node->getNode('node'), $scope),
                );
            }

            $args = [
                ...$args,
                ...$this->nodeMapper->map(
                    $node->hasNode('arguments') ? $node->getNode('arguments') : new Node(),
                    fn(AbstractExpression $node) => new Arg(
                        $delegator->transform($node, $scope),
                    ),
                ),
            ];

            $callable = $twigFunctionOrFilterOrTest->getCallable();

            if (is_string($callable)) {
                return new FuncCall(
                    new Name($callable),
                    $args,
                );
            }

            if (is_array($callable)) {
                [$class, $method] = $callable;

                if (is_object($class)) {
                    $class = $class::class;
                }

                return new MethodCall(
                    new FuncCall(
                        new Name('twigstan_get_object'),
                        [new Arg(new ClassConstFetch(new Name\FullyQualified($class), 'class'))],
                    ),
                    $method,
                    $args,
                );
            }

            if ($callable instanceof Closure) {
                $reflection = new ReflectionFunction($callable);
                $class = $reflection->getClosureScopeClass()?->getName();
                $method = $reflection->getName();

                if ($class === null) {
                    return new FuncCall(
                        new Name($method),
                        $args,
                    );
                }

                return new MethodCall(
                    new FuncCall(
                        new Name('twigstan_get_object'),
                        [new Arg(new ClassConstFetch(new Name\FullyQualified($class), 'class'))],
                    ),
                    $method,
                    $args,
                );
            }

            if ($callable === null) {
                return new String_('no callable');
            }

            throw new ShouldNotHappenException(sprintf('Unsupported expression "%s"', $node::class));
        }
    }

}

<?php

declare(strict_types=1);

namespace TwigStan\Symfony;

use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Printer\Printer;
use ReflectionMethod;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use TwigStan\Processing\TemplateContext;
use TwigStan\Twig\SourceLocation;
use TwigStan\Twig\TwigFactory;

/**
 * Types app.user as not null in templates rendered by controllers whose routes require a user,
 * according to the access control of Symfony security and #[IsGranted] attributes.
 */
final class AppUserContext
{
    /**
     * Security attributes that are only granted to a user. Other attributes are decided by voters.
     */
    private const array USER_ATTRIBUTES = ['IS_AUTHENTICATED', 'IS_AUTHENTICATED_FULLY', 'IS_AUTHENTICATED_REMEMBERED', 'IS_IMPERSONATOR'];

    /**
     * @var null|array<string, list<array{ReflectionMethod, list<array{string, Route}>}>> The controllers and their routes by file
     */
    private ?array $controllers = null;

    private RouterInterface $router;

    private ?AccessMapInterface $accessMap;

    public function __construct(
        private readonly TwigFactory $twigFactory,
        private readonly PhpDocParser $phpDocParser,
        private readonly Lexer $lexer,
        private readonly ?string $appUserClass,
    ) {}

    public function apply(TemplateContext $templateContext): TemplateContext
    {
        $container = $this->twigFactory->getContainer();

        if ($container === null) {
            return $templateContext;
        }

        $router = $container->get('router');
        $accessMap = $container->has('security.access_map') ? $container->get('security.access_map') : null;

        if ( ! $router instanceof RouterInterface) {
            return $templateContext;
        }

        $this->router = $router;
        $this->accessMap = $accessMap instanceof AccessMapInterface ? $accessMap : null;
        $this->controllers ??= $this->findControllers();

        $context = [];
        foreach ($templateContext->context as $template => $renderPoints) {
            foreach ($renderPoints as $hash => [$sourceLocation, $renderContext]) {
                if ($this->requiresUser($sourceLocation)) {
                    $renderContext = $this->withUser($renderContext);
                }

                $context[$template][$hash] = [$sourceLocation, $renderContext];
            }
        }

        return new TemplateContext($context);
    }

    private function requiresUser(SourceLocation $sourceLocation): bool
    {
        $controllers = array_filter(
            $this->controllers[$sourceLocation->fileName] ?? [],
            fn(array $controller) => $sourceLocation->lineNumber >= $controller[0]->getStartLine() && $sourceLocation->lineNumber <= $controller[0]->getEndLine(),
        );

        // Templates rendered outside of a controller can be rendered without a request.
        if ($controllers === []) {
            return false;
        }

        foreach ($controllers as [$method, $routes]) {
            if ($this->isGrantedRequiresUser($method)) {
                continue;
            }

            foreach ($routes as [$name, $route]) {
                if ( ! $this->routeRequiresUser($name, $route)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isGrantedRequiresUser(ReflectionMethod $method): bool
    {
        foreach ([...$method->getDeclaringClass()->getAttributes(IsGranted::class), ...$method->getAttributes(IsGranted::class)] as $attribute) {
            $arguments = $attribute->getArguments();

            // Every #[IsGranted] must be granted, so one that requires a user is enough.
            if ($this->requiresUserAttributes([$arguments['attribute'] ?? $arguments[0] ?? null])) {
                return true;
            }
        }

        return false;
    }

    private function routeRequiresUser(string $name, Route $route): bool
    {
        if ($this->accessMap === null) {
            return false;
        }

        $request = Request::create(
            sprintf(
                '%s://%s%s',
                in_array('https', $route->getSchemes(), true) ? 'https' : 'http',
                $route->getHost() !== '' ? $this->fillPlaceholders($route, $route->getHost()) : 'localhost',
                $this->fillPlaceholders($route, $route->getPath()),
            ),
            $route->getMethods()[0] ?? 'GET',
        );
        $request->attributes->set('_route', $name);

        [$attributes] = $this->accessMap->getPatterns($request);

        return $this->requiresUserAttributes($attributes ?? []);
    }

    /**
     * Access is granted when one of the attributes is granted, so all of them must require a user.
     *
     * @param array<mixed> $attributes
     */
    private function requiresUserAttributes(array $attributes): bool
    {
        if ($attributes === []) {
            return false;
        }

        foreach ($attributes as $attribute) {
            if ( ! is_string($attribute) || ( ! str_starts_with($attribute, 'ROLE_') && ! in_array($attribute, self::USER_ATTRIBUTES, true))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Replaces the placeholders of a route with values that match their requirements, such as /orgs/{org} with /orgs/1.
     */
    private function fillPlaceholders(Route $route, string $pattern): string
    {
        return (string) preg_replace_callback(
            '#\{!?(\w+)(?:<([^>]*)>)?(?:\?[^}]*)?\}#',
            function (array $match) use ($route): string {
                $requirement = ($match[2] ?? '') !== '' ? $match[2] : $route->getRequirement($match[1]);
                $default = $route->getDefault($match[1]);

                foreach ([is_scalar($default) ? (string) $default : null, '1', 'a', 'a-1'] as $value) {
                    if ($value !== null && ($requirement === null || @preg_match(sprintf('#^(?:%s)$#u', $requirement), $value) === 1)) {
                        return $value;
                    }
                }

                return '1';
            },
            $pattern,
        );
    }

    /**
     * @return array<string, list<array{ReflectionMethod, list<array{string, Route}>}>>
     */
    private function findControllers(): array
    {
        $methods = [];
        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');

            if (is_string($controller)) {
                $controller = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
            }

            if ( ! is_array($controller) || ! is_string($controller[0] ?? null) || ! is_string($controller[1] ?? null) || ! method_exists($controller[0], $controller[1])) {
                continue;
            }

            $method = new ReflectionMethod($controller[0], $controller[1]);
            $key = sprintf('%s::%s', $method->getDeclaringClass()->getName(), $method->getName());

            $methods[$key] ??= [$method, []];
            $methods[$key][1][] = [$name, $route];
        }

        $controllers = [];
        foreach ($methods as [$method, $routes]) {
            $fileName = $method->getFileName();

            if ($fileName === false || ($fileName = realpath($fileName)) === false) {
                continue;
            }

            $controllers[$fileName][] = [$method, $routes];
        }

        return $controllers;
    }

    private function withUser(string $context): string
    {
        $tagValue = $this->phpDocParser->parseTagValue(new TokenIterator($this->lexer->tokenize($context)), '@var');

        if ( ! $tagValue instanceof VarTagValueNode || ! $tagValue->type instanceof ArrayShapeNode) {
            return $context;
        }

        $shape = $tagValue->type;
        $shape->items = array_values(array_filter($shape->items, fn(ArrayShapeItemNode $item) => (string) $item->keyName !== 'app'));
        $shape->items[] = new ArrayShapeItemNode(
            new IdentifierTypeNode('app'),
            false,
            new GenericTypeNode(
                new IdentifierTypeNode('\\' . AppVariable::class),
                [new IdentifierTypeNode('\\' . ($this->appUserClass ?? UserInterface::class))],
            ),
        );

        return (new Printer())->print($shape);
    }
}

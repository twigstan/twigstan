<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\TypeNodeResolver;

use PHPStan\Analyser\NameScope;
use PHPStan\DependencyInjection\AutowiredService;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\LateResolvableType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

#[AutowiredService]
final class PreservedContextTypeNodeResolver implements TypeNodeResolverExtension, TypeNodeResolverAwareExtension
{
    private TypeNodeResolver $resolver;

    /**
     * @var array<int, true>
     */
    private array $resolving = [];

    public function setTypeNodeResolver(TypeNodeResolver $typeNodeResolver): void
    {
        $this->resolver = $typeNodeResolver;
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        if ( ! $typeNode instanceof ArrayShapeNode || ! str_starts_with($nameScope->getClassName() ?? '', '__TwigTemplate_')) {
            return null;
        }

        $id = spl_object_id($typeNode);

        if (isset($this->resolving[$id])) {
            return null;
        }

        $this->resolving[$id] = true;

        try {
            // Resolve nested shapes through this extension before the default resolver combines them.
            $type = $this->resolver->resolve($typeNode, $nameScope);

            return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type {
                if ($type instanceof LateResolvableType) {
                    $type = $type->resolve();
                }

                // @phpstan-ignore phpstanApi.instanceofType (The preservation API belongs to the concrete shape.)
                if ($type instanceof ConstantArrayType) {
                    return $type->withPreservedArrayShape();
                }

                return $traverse($type);
            });
        } finally {
            unset($this->resolving[$id]);
        }
    }
}

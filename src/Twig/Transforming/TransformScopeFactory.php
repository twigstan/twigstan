<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TransformScopeFactory
{
    /**
     * @var array<string, string>
     */
    private array $globals;

    /**
     * @param array<array{variable: string, type: string}> $globals
     */
    public function __construct(
        #[Autowire(param: 'twigstan.globals')]
        array $globals,
    ) {
        $this->globals = array_combine(
            array_column($globals, 'variable'),
            array_column($globals, 'type'),
        );
    }

    public function create(array $requirements = []): TransformScope
    {
        return new TransformScope($this->globals, $requirements);
    }

}

<?php

declare(strict_types=1);

namespace TwigStan\DependencyInjection;

use Nette\DI\Config\Loader;

final class Configurator extends \Nette\Bootstrap\Configurator
{
    public function __construct(private Loader $loader)
    {
        parent::__construct();
    }

    protected function createLoader(): Loader
    {
        return $this->loader;
    }
}

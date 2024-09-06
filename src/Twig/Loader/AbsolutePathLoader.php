<?php

declare(strict_types=1);

namespace TwigStan\Twig\Loader;

use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final readonly class AbsolutePathLoader implements LoaderInterface
{
    public function __construct(
        private FilesystemLoader $loader,
    ) {}

    public function getLoader(): FilesystemLoader
    {
        return $this->loader;
    }

    public function maybeResolveAbsolutePath(string $name): string
    {
        if (str_starts_with($name, '@')) {
            return $name;
        }

        foreach ($this->loader->getNamespaces() as $namespace) {
            foreach ($this->loader->getPaths($namespace) as $path) {
                $path = rtrim($path, DIRECTORY_SEPARATOR);
                if (str_starts_with($name, $path)) {
                    return sprintf('@%s/%s', $namespace, ltrim(substr($name, strlen($path)), DIRECTORY_SEPARATOR));
                }
            }
        }

        return sprintf('@%s/%s', FilesystemLoader::MAIN_NAMESPACE, $name);
    }

    public function getSourceContext(string $name): Source
    {
        return $this->loader->getSourceContext($this->maybeResolveAbsolutePath($name));
    }

    public function getCacheKey(string $name): string
    {
        return $this->loader->getCacheKey($this->maybeResolveAbsolutePath($name));
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->loader->isFresh($this->maybeResolveAbsolutePath($name), $time);
    }

    public function exists(string $name)
    {
        return $this->loader->exists($this->maybeResolveAbsolutePath($name));
    }
}

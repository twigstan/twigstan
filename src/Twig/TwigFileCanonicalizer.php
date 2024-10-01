<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use RuntimeException;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

final class TwigFileCanonicalizer
{
    private FilesystemLoader $loader;

    /**
     * @var array<string, list<string>>
     */
    private array $namespacesWithinMainNamespace;

    public function __construct(
        private Environment $twig,
    ) {}

    /**
     * Takes a relative or absolute path and returns a canonicalized Twig path.
     *
     * Examples:
     * layout.html > @__main__/layout.html
     * @__main__/layout.html > @__main__/layout.html
     * @Admin/layout.html > @Admin/layout.html
     * /Path/To/Project/templates/layout.html > @__main__/layout.html
     * /Path/To/Project/admin/layout.html > @Admin/layout.html
     *
     * @throws UnableToCanonicalizeTwigFileException
     */
    public function canonicalize(string $name): string
    {
        $this->loader ??= $this->initializeLoader();
        $this->namespacesWithinMainNamespace ??= $this->findNamespacesWithinMainNamespace();

        if (Path::isAbsolute($name)) {
            foreach ($this->loader->getNamespaces() as $namespace) {
                foreach ($this->loader->getPaths($namespace) as $path) {
                    $path = rtrim($path, DIRECTORY_SEPARATOR);
                    if (str_starts_with($name, $path)) {
                        $twigPath = sprintf('@%s/%s', $namespace, ltrim(substr($name, strlen($path)), DIRECTORY_SEPARATOR));
                        if ($this->loader->exists($twigPath)) {
                            return $twigPath;
                        }
                    }
                }
            }
        }

        if ( ! str_starts_with($name, '@')) {
            $name = sprintf('@%s/%s', FilesystemLoader::MAIN_NAMESPACE, $name);
        }

        if ($this->loader->exists($name)) {
            [$namespace, $fileName] = explode('/', substr($name, 1), 2);

            foreach ($this->namespacesWithinMainNamespace[$namespace] ?? [] as $path) {
                $nameWithinMain = sprintf('@%s/%s', FilesystemLoader::MAIN_NAMESPACE, Path::join($path, $fileName));
                if ($this->loader->exists($nameWithinMain)) {
                    return $nameWithinMain;
                }
            }

            return $name;
        }

        throw new UnableToCanonicalizeTwigFileException(sprintf('Unable to resolve path for "%s"', $name));
    }

    /**
     * @return array<string, list<string>>
     */
    private function findNamespacesWithinMainNamespace(): array
    {
        if ($this->loader->getNamespaces() === []) {
            throw new RuntimeException('No namespaces found in the Twig loader.');
        }

        $mainPaths = $this->loader->getPaths();

        $namespacesWithinMainNamespace = [];
        foreach ($this->loader->getNamespaces() as $namespace) {
            if ($namespace === FilesystemLoader::MAIN_NAMESPACE) {
                continue;
            }

            foreach ($this->loader->getPaths($namespace) as $path) {
                foreach ($mainPaths as $mainPath) {
                    if ( ! str_starts_with($path, $mainPath)) {
                        continue;
                    }
                    $namespacesWithinMainNamespace[$namespace][] = ltrim(substr($path, strlen($mainPath)), DIRECTORY_SEPARATOR);
                }
            }
        }

        return $namespacesWithinMainNamespace;
    }

    private function initializeLoader(): FilesystemLoader
    {
        $loader = $this->twig->getLoader();

        if ($loader instanceof FilesystemLoader) {
            return $loader;
        }

        if ($loader instanceof ChainLoader) {
            foreach ($loader->getLoaders() as $innerLoader) {
                if ($innerLoader instanceof FilesystemLoader) {
                    return $innerLoader;
                }
            }
        }

        throw new RuntimeException(sprintf('Expected to find FilesystemLoader in the Twig environment, but got %s instead.', $loader::class));
    }
}

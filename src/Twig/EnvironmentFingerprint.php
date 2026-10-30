<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use Twig\Environment;

/**
 * Hashes the code of the Twig environment: its extensions and the closures of its functions, filters and tests.
 *
 * TwigStan types the compiled templates by the Twig environment, which PHPStan does not know as a dependency
 * of the templates. The fingerprint is part of PHPStan's configuration, so a change discards its result cache.
 */
final class EnvironmentFingerprint
{
    private ?string $fingerprint = null;

    public function __construct(
        private readonly Environment $twig,
        private readonly ?string $environmentLoader,
    ) {}

    public function get(): string
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        $files = $this->environmentLoader !== null ? [$this->environmentLoader => true] : [];

        foreach ($this->twig->getExtensions() as $extension) {
            $files[(string) (new ReflectionClass($extension))->getFileName()] = true;
        }

        foreach ([...$this->twig->getFunctions(), ...$this->twig->getFilters(), ...$this->twig->getTests()] as $twigCallable) {
            $callable = $twigCallable->getCallable();

            if ($callable instanceof Closure) {
                $files[(string) (new ReflectionFunction($callable))->getFileName()] = true;
            } elseif (is_array($callable) && (is_object($callable[0]) || class_exists($callable[0]))) {
                $files[(string) (new ReflectionClass($callable[0]))->getFileName()] = true;
            }
        }

        unset($files['']);
        ksort($files);

        $hashes = [];
        foreach (array_keys($files) as $file) {
            $hashes[] = $file . ':' . (is_file($file) ? hash_file('xxh128', $file) : '');
        }

        return $this->fingerprint = hash('xxh128', implode("\n", $hashes));
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * @template-implements IteratorAggregate<string, ScopeInjectionResult>
 */
final readonly class ScopeInjectionResultCollection implements IteratorAggregate
{
    /**
     * @var array<string, ScopeInjectionResult>
     */
    private array $results;

    public function __construct(ScopeInjectionResult ...$results)
    {
        $results = array_values($results);

        $this->results = array_combine(
            array_map(
                fn(ScopeInjectionResult $result) => $result->twigFileName,
                $results,
            ),
            $results,
        );
    }

    public function with(ScopeInjectionResult ...$results): self
    {
        $data = $this->results;
        foreach ($results as $transformResult) {
            $data[$transformResult->phpFile] = $transformResult;
        }

        return new self(...$data);
    }

    /**
     * @return ArrayIterator<string, ScopeInjectionResult>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->results);
    }

    public function hasPhpFile(string $phpFile): bool
    {
        foreach ($this->results as $result) {
            if ($result->phpFile === $phpFile) {
                return true;
            }
        }

        return false;
    }

    public function getByPhpFile(string $phpFile): ScopeInjectionResult
    {
        foreach ($this->results as $result) {
            if ($result->phpFile === $phpFile) {
                return $result;
            }
        }

        throw new InvalidArgumentException(sprintf('No ScopeInjectionResult found for PHP file "%s".', $phpFile));
    }

    public function hasTwigFileName(string $fileName): bool
    {
        return isset($this->results[$fileName]);
    }

    public function getByTwigFileName(string $fileName): ScopeInjectionResult
    {
        return $this->results[$fileName] ?? throw new InvalidArgumentException(sprintf('No ScopeInjectionResult found for Twig file "%s".', $fileName));
    }
}

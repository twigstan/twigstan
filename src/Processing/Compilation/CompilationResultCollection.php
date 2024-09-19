<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

use ArrayIterator;
use IteratorAggregate;

/**
 * @template-implements IteratorAggregate<string, CompilationResult>
 */
final readonly class CompilationResultCollection implements IteratorAggregate
{
    /**
     * @var array<string, CompilationResult>
     */
    private array $results;

    public function __construct(CompilationResult ...$results)
    {
        $results = array_values($results);

        $this->results = array_combine(
            array_map(
                fn(CompilationResult $result) => $result->twigFileName,
                $results,
            ),
            $results,
        );

    }

    public function with(CompilationResult ...$results): self
    {
        $data = $this->results;
        foreach ($results as $transformResult) {
            $data[$transformResult->phpFile] = $transformResult;
        }

        return new self(...$data);
    }

    /**
     * @return ArrayIterator<string, CompilationResult>
     */
    public function getIterator(): \ArrayIterator
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

    public function getByPhpFile(string $phpFile): CompilationResult
    {
        foreach ($this->results as $result) {
            if ($result->phpFile === $phpFile) {
                return $result;
            }
        }

        throw new \InvalidArgumentException(sprintf('No CompilationResult found for PHP file "%s"', $phpFile));
    }

    public function hasTwigFileName(string $fileName): bool
    {
        return isset($this->results[$fileName]);
    }

    public function getByTwigFileName(string $fileName): CompilationResult
    {
        return $this->results[$fileName] ?? throw new \InvalidArgumentException(sprintf('No CompilationResult found for Twig file "%s"', $fileName));
    }
}

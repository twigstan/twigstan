<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening;

use ArrayIterator;
use IteratorAggregate;

/**
 * @template-implements IteratorAggregate<string, FlatteningResult>
 */
final readonly class FlatteningResultCollection implements IteratorAggregate
{
    /**
     * @var array<string, FlatteningResult>
     */
    private array $results;

    public function __construct(FlatteningResult ...$results)
    {
        $results = array_values($results);

        $this->results = array_combine(
            array_map(
                fn(FlatteningResult $result) => $result->twigFileName,
                $results,
            ),
            $results,
        );

    }

    public function with(FlatteningResult ...$results): self
    {
        $data = $this->results;
        foreach ($results as $transformResult) {
            $data[$transformResult->phpFile] = $transformResult;
        }

        return new self(...$data);
    }

    /**
     * @return ArrayIterator<string, FlatteningResult>
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

    public function getByPhpFile(string $phpFile): FlatteningResult
    {
        foreach ($this->results as $result) {
            if ($result->phpFile === $phpFile) {
                return $result;
            }
        }

        throw new \InvalidArgumentException(sprintf('No FlatteningResult found for PHP file "%s"', $phpFile));
    }

    public function hasTwigFileName(string $fileName): bool
    {
        return isset($this->results[$fileName]);
    }

    public function getByTwigFileName(string $fileName): FlatteningResult
    {
        return $this->results[$fileName] ?? throw new \InvalidArgumentException(sprintf('No FlatteningResult found for Twig file "%s"', $fileName));
    }
}

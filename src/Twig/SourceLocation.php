<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use IteratorAggregate;
use Stringable;
use Symfony\Component\Filesystem\Path;
use Traversable;

/**
 * @implements IteratorAggregate<SourceLocation>
 */
final readonly class SourceLocation implements Stringable, IteratorAggregate
{
    public function __construct(
        public string $fileName,
        public int $lineNumber,
        public ?self $previous = null,
    ) {}

    public static function append(?self $current, self $toAppend): self
    {
        if ($current === null) {
            return $toAppend;
        }

        /**
         * @var non-empty-list<SourceLocation> $nodes
         */
        $nodes = iterator_to_array($current);

        $last = $nodes[array_key_last($nodes)];

        if ($last->fileName === $toAppend->fileName && $last->lineNumber === $toAppend->lineNumber) {
            return $current;
        }

        $nodes[] = $toAppend;

        $sourceLocation = null;
        foreach (array_reverse($nodes) as $node) {
            $sourceLocation = new self(
                $node->fileName,
                $node->lineNumber,
                $sourceLocation,
            );
        }

        return $sourceLocation;
    }

    /**
     * @param array<mixed> $data
     */
    public static function decode(array $data): self
    {
        return new self(
            $data['fileName'],
            $data['lineNumber'],
            isset($data['previous']) ? self::decode($data['previous']) : null,
        );
    }

    public function toString(?string $relativeToDirectory = null): string
    {
        return sprintf(
            '%s:%d%s',
            $relativeToDirectory !== null ? Path::makeRelative($this->fileName, $relativeToDirectory) : $this->fileName,
            $this->lineNumber,
            $this->previous !== null ? ', ' . $this->previous->toString($relativeToDirectory) : '',
        );
    }

    public function contains(string $fileName): bool
    {
        if ($this->fileName === $fileName) {
            return true;
        }

        return $this->previous?->contains($fileName) ?? false;
    }

    public function last(): self
    {
        $current = $this;
        while ($current->previous !== null) {
            $current = $current->previous;
        }

        return $current;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function getIterator(): Traversable
    {
        $current = $this;
        while ($current !== null) {
            yield $current;
            $current = $current->previous;
        }
    }

    public function getHash(): string
    {
        return hash('crc32b', $this->toString());
    }

    public function sort(SourceLocation $other): int
    {
        if ($this->fileName === $other->fileName) {
            return $this->lineNumber <=> $other->lineNumber;
        }

        return $this->fileName <=> $other->fileName;
    }
}

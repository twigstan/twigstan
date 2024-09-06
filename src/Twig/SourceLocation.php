<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use IteratorAggregate;
use Stringable;
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

    public static function append(self $current, self $toAppend): self
    {
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

    public static function decode(array $data): self
    {
        return new self(
            $data['fileName'],
            $data['lineNumber'],
            isset($data['previous']) ? self::decode($data['previous']) : null,
        );
    }

    public function __toString(): string
    {
        return sprintf(
            '%s:%d%s',
            $this->fileName,
            $this->lineNumber,
            $this->previous !== null ? ', ' . $this->previous : '',
        );
    }

    public function getIterator(): Traversable
    {
        $current = $this;
        while ($current !== null) {
            yield $current;
            $current = $current->previous;
        }
    }

}

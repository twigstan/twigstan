<?php

declare(strict_types=1);

namespace TwigStan\PHP;

use PhpParser\PrettyPrinter\Standard;
use TwigStan\PHP\Node\TwigLineNumberNode;

final class PrettyPrinter extends Standard
{
    private int $sourceLine = 0;

    public function prettyPrintFile(array $stmts): string
    {
        $this->sourceLine = 0;

        return parent::prettyPrintFile($stmts);
    }

    protected function pMaybeMultiline(array $nodes, bool $trailingComma = false): string
    {
        return $this->pCommaSeparatedMultiline($nodes, $trailingComma) . $this->nl;
    }

    protected function pTwigLineNumber(TwigLineNumberNode $node): string
    {
        if ($this->sourceLine === $node->getAttribute('line')) {
            return '';
        }

        $this->sourceLine = $node->getAttribute('line');

        return '// line ' . $this->sourceLine;
    }
}

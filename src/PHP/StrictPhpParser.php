<?php

declare(strict_types=1);

namespace TwigStan\PHP;

use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class StrictPhpParser
{
    private Parser $phpParser;

    public function __construct(
        private Filesystem $filesystem,
    ) {
        $this->phpParser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return list<Stmt>
     */
    public function parse(string $code): array
    {
        $stmts = $this->phpParser->parse($code);

        if ($stmts === null) {
            throw new RuntimeException('Failed to parse PHP code');
        }

        return array_values($stmts);
    }

    /**
     * @return list<Stmt>
     */
    public function parseFile(string $phpFile): array
    {
        return $this->parse($this->filesystem->readFile($phpFile));
    }

}

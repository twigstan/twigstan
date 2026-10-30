<?php

declare(strict_types=1);

namespace TwigStan\Error;

use PhpParser\Node;
use PhpParser\NodeFinder;
use TwigStan\PHP\StrictPhpParser;
use TwigStan\PHPStan\Analysis\Error;
use TwigStan\Processing\TemplateContext;

/**
 * Decides whether an error is in code that is analyzed with a known context.
 */
final class TemplateWithoutContextFilter
{
    /**
     * @var array<string, list<array{int, int}>> The lines of the macros by compiled file
     */
    private array $macroLines = [];

    public function __construct(
        private readonly StrictPhpParser $phpParser,
    ) {}

    public function hasContext(Error $error, TemplateContext $templateContext): bool
    {
        if ($error->twigFile === null || $templateContext->hasTemplate($error->twigFile)) {
            return true;
        }

        // Templates that are only used for their macros are analyzed with the arguments of the macro calls,
        // but their blocks are rendered with the context of the template that renders them.
        if ($templateContext->getUniqueContextByMacro($error->twigFile) === [] || $error->phpFile === null) {
            return false;
        }

        foreach ($this->getMacroLines($error->phpFile) as [$startLine, $endLine]) {
            if ($error->phpLine >= $startLine && $error->phpLine <= $endLine) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{int, int}>
     */
    private function getMacroLines(string $phpFile): array
    {
        if (isset($this->macroLines[$phpFile])) {
            return $this->macroLines[$phpFile];
        }

        // Embedded templates are compiled to more classes after the class of the template.
        $class = (new NodeFinder())->findFirstInstanceOf($this->phpParser->parseFile($phpFile), Node\Stmt\Class_::class);

        $lines = [];
        foreach ($class?->getMethods() ?? [] as $method) {
            // Twig declares the macros as closures in loadDeclaredMacros().
            if ($method->name->name === 'loadDeclaredMacros') {
                $lines[] = [$method->getStartLine(), $method->getEndLine()];
            }
        }

        return $this->macroLines[$phpFile] = $lines;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Node\ModuleNode;
use TwigStan\PHP\PrettyPrinter;
use TwigStan\PHP\StrictPhpParser;
use TwigStan\Processing\Compilation\Parser\TwigNodeParser;
use TwigStan\Processing\Compilation\PhpVisitor\AppendFilePathToLineCommentVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\AssignGetDefinedVarsInParentVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\IgnoreArgumentTemplateTypeOnEnsureTraversableVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RefactorLoadTemplateYieldVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RefactorStaticCaptureOutputCallVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RefactorStaticIncludeCallVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RefactorStaticMacroCallVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RefactorYieldBlockVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveForeachArrayIntersectVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveImportMacroVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveImportsVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveParentAssignVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveParentUnsetVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveParentYieldVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveUnwrapVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\ReplaceExtensionsArrayDimFetchToMethodCallVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\ReplaceWithSimplifiedTwigTemplateVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\UnwrapContextVariableNodeVisitor;

final readonly class TwigCompiler
{
    public function __construct(
        private TwigNodeParser $twigNodeParser,
        private PrettyPrinter $prettyPrinter,
        private Filesystem $filesystem,
        private ModifiedCompiler $compiler,
        private StrictPhpParser $phpParser,
        private TwigGlobalsToPhpDoc $twigGlobalsToPhpDoc,
    ) {}

    public function compile(ModuleNode | string $template, string $targetDirectory): CompilationResult
    {
        $template = $this->twigNodeParser->parse($template);

        if ($template->getSourceContext() === null) {
            throw new RuntimeException('Template does not have a source context');
        }

        $twigFilePath = $template->getSourceContext()->getPath();

        $phpSource = $this->compiler->compile($template)->getSource();

        $stmts = $this->phpParser->parse($phpSource);

        $stmts = $this->applyVisitors(
            $stmts,
            new NameResolver(),
            new AppendFilePathToLineCommentVisitor($template->getSourceContext()->getName()),
            new RemoveImportsVisitor(),
            new ReplaceWithSimplifiedTwigTemplateVisitor($this->twigGlobalsToPhpDoc),
            new RemoveUnwrapVisitor(),
            new RefactorYieldBlockVisitor(),
            new RefactorStaticIncludeCallVisitor(),
            new RemoveImportMacroVisitor(),
            new RefactorStaticMacroCallVisitor(),
            new RefactorLoadTemplateYieldVisitor(),
            new RefactorStaticCaptureOutputCallVisitor(),
            new RemoveParentYieldVisitor(),
            new RemoveParentUnsetVisitor(),
            new AssignGetDefinedVarsInParentVisitor(),
            new RemoveParentAssignVisitor(),
            new RemoveForeachArrayIntersectVisitor(),
            new UnwrapContextVariableNodeVisitor(),
            new IgnoreArgumentTemplateTypeOnEnsureTraversableVisitor(),
            new ReplaceExtensionsArrayDimFetchToMethodCallVisitor(),
        );

        $phpSource = $this->prettyPrinter->prettyPrintFile($stmts);

        $phpFile = Path::join($targetDirectory, sprintf(
            '%s.%s.php',
            basename($twigFilePath),
            hash('crc32', $twigFilePath),
        ));

        $this->filesystem->dumpFile(
            $phpFile,
            $phpSource,
        );

        return new CompilationResult(
            $template->getSourceContext()->getName(),
            $twigFilePath,
            $phpFile,
        );
    }

    /**
     * @param array<Node> $stmts
     *
     * @return array<Node>
     */
    private function applyVisitors(array $stmts, NodeVisitor ...$visitors): array
    {
        foreach ($visitors as $visitor) {
            $nodeTraverser = new NodeTraverser();
            $nodeTraverser->addVisitor($visitor);
            $stmts = $nodeTraverser->traverse($stmts);
        }

        return $stmts;
    }
}

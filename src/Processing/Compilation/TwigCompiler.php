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
use Twig\Environment;
use Twig\Node\ModuleNode;
use TwigStan\PHP\PrettyPrinter;
use TwigStan\PHP\StrictPhpParser;
use TwigStan\Processing\Compilation\Parser\TwigNodeParser;
use TwigStan\Processing\Compilation\PhpVisitor\AddExtraLineNumberCommentVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\AddGetExtensionMethodVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\AddTypeCommentsToTemplateVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\AppendFilePathToLineCommentVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\IgnoreArgumentTemplateTypeOnEnsureTraversableVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\MakeFinalVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RefactorExtensionCallVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RefactorLoopClosureVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveImportsVisitor;
use TwigStan\Processing\Compilation\PhpVisitor\RemoveLineNumberFromGetAttributeCallVisitor;
use TwigStan\Processing\TemplateContext;
use TwigStan\Processing\TemplateContextToArrayShape;

final readonly class TwigCompiler
{
    public function __construct(
        private TwigNodeParser $twigNodeParser,
        private PrettyPrinter $prettyPrinter,
        private Filesystem $filesystem,
        private ModifiedCompiler $compiler,
        private StrictPhpParser $phpParser,
        private TemplateContextToArrayShape $templateContextToArrayShape,
    ) {}

    public function compile(ModuleNode | string $template, string $targetDirectory, TemplateContext $templateContext, int $run): CompilationResult
    {
        $targetDirectory = Path::join($targetDirectory, (string) $run);

        $this->filesystem->mkdir($targetDirectory);

        $twigNode = $this->twigNodeParser->parse($template);

        if ($twigNode->getSourceContext() === null) {
            throw new RuntimeException('Template does not have a source context.');
        }

        $twigFilePath = Path::canonicalize($twigNode->getSourceContext()->getPath());

        $phpSource = $this->compiler->compile($twigNode)->getSource();

        $this->filesystem->dumpFile(
            Path::join($targetDirectory, sprintf(
                '%s.original.%s.php',
                basename($twigFilePath),
                hash('crc32', $twigFilePath),
            )),
            $phpSource,
        );

        $stmts = $this->phpParser->parse($phpSource);

        $stmts = $this->applyVisitors(
            $stmts,
            new NameResolver(),
            new MakeFinalVisitor(),
            new AddExtraLineNumberCommentVisitor(),
            new AppendFilePathToLineCommentVisitor($twigFilePath),
            new RemoveImportsVisitor(),
            new AddTypeCommentsToTemplateVisitor($this->templateContextToArrayShape->getByTemplate($templateContext, $twigFilePath)),
            new IgnoreArgumentTemplateTypeOnEnsureTraversableVisitor(),
            new RemoveLineNumberFromGetAttributeCallVisitor(),
            new AddGetExtensionMethodVisitor(),
            new RefactorExtensionCallVisitor(),
            ...(Environment::MAJOR_VERSION >= 4 ? [new RefactorLoopClosureVisitor()] : []),
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

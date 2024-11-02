<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Node\ModuleNode;
use TwigStan\PHP\PrettyPrinter;
use TwigStan\PHP\StrictPhpParser;
use TwigStan\PHPStan\Analysis\CollectedData;
use TwigStan\PHPStan\Collector\TemplateContextCollector;
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
use TwigStan\Processing\ScopeInjection\ArrayShapeMerger;
use TwigStan\Twig\TwigFileCanonicalizer;
use TwigStan\Twig\UnableToCanonicalizeTwigFileException;

final readonly class TwigCompiler
{
    public function __construct(
        private TwigNodeParser $twigNodeParser,
        private PrettyPrinter $prettyPrinter,
        private Filesystem $filesystem,
        private ModifiedCompiler $compiler,
        private StrictPhpParser $phpParser,
        private ArrayShapeMerger $arrayShapeMerger,
        private TwigFileCanonicalizer $twigFileCanonicalizer,
    ) {}

    /**
     * @param list<CollectedData> $collectedData
     */
    public function compile(ModuleNode | string $template, string $targetDirectory, array $collectedData): CompilationResult
    {
        $lexer = new Lexer();
        $constExprParser = new ConstExprParser(true, true);
        $typeParser = new TypeParser($constExprParser, true);
        $phpDocParser = new PhpDocParser($typeParser, $constExprParser);

        $twigNode = $this->twigNodeParser->parse($template);

        if ($twigNode->getSourceContext() === null) {
            throw new RuntimeException('Template does not have a source context.');
        }

        $twigFileName = $twigNode->getSourceContext()->getName();
        $twigFilePath = $twigNode->getSourceContext()->getPath();

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

        $templateRenderContexts = [];
        foreach ($collectedData as $data) {
            if (is_a($data->collecterType, TemplateContextCollector::class, true)) {
                foreach ($data->data as $renderData) {
                    try {
                        $template = $this->twigFileCanonicalizer->canonicalize($renderData['template']);

                        $templateRenderContexts[$template][] = $renderData['context'];
                    } catch (UnableToCanonicalizeTwigFileException) {
                        // Ignore
                    }
                }
            }
        }

        $templateRenderContext = [];
        foreach ($templateRenderContexts as $templateToRender => $contexts) {
            $newContext = null;
            foreach (array_unique($contexts) as $context) {
                $contextShape = new ArrayShapeNode([]);

                if ($context !== 'array{}') {
                    $phpDocNode = $phpDocParser->parseTagValue(
                        new TokenIterator($lexer->tokenize($context)),
                        '@var',
                    );

                    if ( ! $phpDocNode instanceof VarTagValueNode) {
                        continue;
                    }

                    $contextShape = $phpDocNode->type;

                    if ( ! $contextShape instanceof ArrayShapeNode) {
                        $contextShape = new ArrayShapeNode([]);
                    }
                }

                if ($newContext === null) {
                    $newContext = $contextShape;

                    continue;
                }

                $newContext = $this->arrayShapeMerger->merge(
                    $newContext,
                    $contextShape,
                );
            }

            $templateRenderContext[$templateToRender] = $newContext;
        }

        $stmts = $this->applyVisitors(
            $stmts,
            new NameResolver(),
            new MakeFinalVisitor(),
            new AddExtraLineNumberCommentVisitor(),
            new AppendFilePathToLineCommentVisitor($twigFileName),
            new RemoveImportsVisitor(),
            new AddTypeCommentsToTemplateVisitor($templateRenderContext[$twigFileName] ?? new ArrayShapeNode([])),
            new IgnoreArgumentTemplateTypeOnEnsureTraversableVisitor(),
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
            $twigFileName,
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

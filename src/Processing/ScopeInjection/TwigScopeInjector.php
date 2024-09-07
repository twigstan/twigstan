<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection;

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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use TwigStan\PHP\PrettyPrinter;
use TwigStan\PHP\StrictPhpParser;
use TwigStan\PHPStan\Analysis\CollectedData;
use TwigStan\PHPStan\Collector\BlockContextCollector;
use TwigStan\PHPStan\Collector\ContextFromRenderMethodCallCollector;
use TwigStan\PHPStan\Collector\ContextFromReturnedArrayWithTemplateAttributeCollector;
use TwigStan\Processing\Flattening\FlatteningResultCollection;
use TwigStan\Processing\ScopeInjection\PhpVisitor\InjectContextVisitor;
use TwigStan\Processing\ScopeInjection\PhpVisitor\PhpToTemplateLinesNodeVisitor;
use TwigStan\Twig\SourceLocation;
use TwigStan\Twig\TwigFileNormalizer;

final readonly class TwigScopeInjector
{
    public function __construct(
        private PrettyPrinter $prettyPrinter,
        private Filesystem $filesystem,
        private StrictPhpParser $phpParser,
        private ArrayShapeMerger $arrayShapeMerger,
        private TwigFileNormalizer $twigFileNormalizer,
    ) {}

    /**
     * @param list<CollectedData> $collectedData
     */
    public function inject(array $collectedData, FlatteningResultCollection $collection, string $targetDirectory): ScopeInjectionResultCollection
    {
        $lexer = new Lexer();
        $constExprParser = new ConstExprParser(true, true);
        $typeParser = new TypeParser($constExprParser, true);
        $phpDocParser = new PhpDocParser($typeParser, $constExprParser);

        $contextBeforeBlock = array_map(
            function (CollectedData $collectedData) use ($lexer, $phpDocParser) {
                $phpDocNode = $phpDocParser->parseTagValue(
                    new TokenIterator($lexer->tokenize($collectedData->data['context'])),
                    '@var',
                );

                return [
                    'blockName' => $collectedData->data['blockName'],
                    'sourceLocation' => SourceLocation::decode($collectedData->data['sourceLocation']),
                    'context' => $phpDocNode->type,
                    'parent' => $collectedData->data['parent'],
                ];
            },
            array_filter(
                $collectedData,
                fn($collectedData) => $collectedData->collecterType === BlockContextCollector::class,
            ),
        );

        $templateRenderContexts = [];
        foreach ($collectedData as $data) {
            if ($data->collecterType == ContextFromReturnedArrayWithTemplateAttributeCollector::class) {
                foreach ($data->data as $renderData) {
                    $template = $this->twigFileNormalizer->normalize($renderData['template']);

                    $templateRenderContexts[$template][] = $renderData['context'];
                }
            } elseif ($data->collecterType === ContextFromRenderMethodCallCollector::class) {
                $template = $this->twigFileNormalizer->normalize($data->data['template']);

                $templateRenderContexts[$template][] = $data->data['context'];
            }
        }

        $templateRenderContext = [];
        foreach ($templateRenderContexts as $template => $contexts) {
            $newContext = null;
            foreach (array_unique($contexts) as $context) {
                $phpDocNode = $phpDocParser->parseTagValue(
                    new TokenIterator($lexer->tokenize($context)),
                    '@var',
                );

                if (!$phpDocNode instanceof VarTagValueNode) {
                    continue;
                }

                $contextShape = $phpDocNode->type;

                if (!$contextShape instanceof ArrayShapeNode) {
                    $contextShape = new ArrayShapeNode([]);
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

            $templateRenderContext[$template] = $newContext;
        }

        $results = new ScopeInjectionResultCollection();
        foreach ($collection as $flatteningResult) {
            //$metadata = $this->metadataRegistry->findByTwigFile($flatteningResult->twigFile);

            $stmts = $this->applyVisitors(
                $this->phpParser->parseFile($flatteningResult->phpFile),
                new NameResolver(),
                new InjectContextVisitor(
                    $templateRenderContext[$flatteningResult->twigFileName] ?? new ArrayShapeNode([]),
                    $contextBeforeBlock,
                    $this->arrayShapeMerger,
                ),
            );

            $phpSource = $this->prettyPrinter->prettyPrintFile($stmts);

            //$this->filesystem->dumpFile(
            //    Path::join(dirname($flatteningResult->twigFilePath), basename($flatteningResult->twigFilePath) . '.scope-injected.untrack.php'),
            //    $phpSource,
            //);

            $phpFile = Path::join($targetDirectory, basename($flatteningResult->phpFile));

            $this->filesystem->dumpFile(
                $phpFile,
                $phpSource,
            );

            // This is a bit inefficient, maybe we can make this smarter
            $stmts = $this->phpParser->parse($phpSource);

            $visitor = new PhpToTemplateLinesNodeVisitor();
            $this->applyVisitors($stmts, $visitor);

            $results = $results->with(new ScopeInjectionResult(
                $flatteningResult->twigFileName,
                $flatteningResult->twigFilePath,
                $phpFile,
                $visitor->getMapping(),
            ));
        }

        return $results;
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

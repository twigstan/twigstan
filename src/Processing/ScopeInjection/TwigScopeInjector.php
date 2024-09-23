<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection;

use LogicException;
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
use TwigStan\PHPStan\Collector\TemplateContextCollector;
use TwigStan\Processing\Flattening\FlatteningResultCollection;
use TwigStan\Processing\ScopeInjection\PhpVisitor\InjectContextVisitor;
use TwigStan\Processing\ScopeInjection\PhpVisitor\PhpToTemplateLinesNodeVisitor;
use TwigStan\Twig\SourceLocation;
use TwigStan\Twig\TwigFileCanonicalizer;

final readonly class TwigScopeInjector
{
    public function __construct(
        private PrettyPrinter $prettyPrinter,
        private Filesystem $filesystem,
        private StrictPhpParser $phpParser,
        private ArrayShapeMerger $arrayShapeMerger,
        private TwigFileCanonicalizer $twigFileCanonicalizer,
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

                if (!$phpDocNode instanceof VarTagValueNode) {
                    throw new LogicException('Invalid @var tag');
                }

                $context = $phpDocNode->type;

                if (!$context instanceof ArrayShapeNode) {
                    $context = new ArrayShapeNode([]);
                }

                return [
                    'blockName' => $collectedData->data['blockName'],
                    'sourceLocation' => SourceLocation::decode($collectedData->data['sourceLocation']),
                    'context' => $context,
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
            if (is_a($data->collecterType, TemplateContextCollector::class, true)) {
                foreach ($data->data as $renderData) {
                    $template = $this->twigFileCanonicalizer->canonicalize($renderData['template']);

                    $templateRenderContexts[$template][] = $renderData['context'];
                }
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

            $contextBeforeBlockRelatedToTemplate = array_values(array_filter(
                $contextBeforeBlock,
                fn($contextBeforeBlock) => $contextBeforeBlock['sourceLocation']->contains($flatteningResult->twigFileName),
            ));
            $stmts = $this->applyVisitors(
                $this->phpParser->parseFile($flatteningResult->phpFile),
                new NameResolver(),
                new InjectContextVisitor(
                    $templateRenderContext[$flatteningResult->twigFileName] ?? new ArrayShapeNode([]),
                    $contextBeforeBlockRelatedToTemplate,
                    $this->arrayShapeMerger,
                ),
            );

            $phpSource = $this->prettyPrinter->prettyPrintFile($stmts);

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

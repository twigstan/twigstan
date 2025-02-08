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
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use TwigStan\PHP\PrettyPrinter;
use TwigStan\PHP\StrictPhpParser;
use TwigStan\PHPStan\Analysis\CollectedData;
use TwigStan\PHPStan\Collector\BlockContextCollector;
use TwigStan\PHPStan\Collector\MacroCollector;
use TwigStan\Processing\Flattening\FlatteningResultCollection;
use TwigStan\Processing\ScopeInjection\PhpVisitor\InjectContextVisitor;
use TwigStan\Processing\ScopeInjection\PhpVisitor\InjectMacroVisitor;
use TwigStan\Processing\ScopeInjection\PhpVisitor\PhpToTemplateLinesNodeVisitor;
use TwigStan\Twig\SourceLocation;

final readonly class TwigScopeInjector
{
    public function __construct(
        private PrettyPrinter $prettyPrinter,
        private Filesystem $filesystem,
        private StrictPhpParser $phpParser,
        private ArrayShapeMerger $arrayShapeMerger,
        private PhpDocParser $phpDocParser,
        private Lexer $lexer,
    ) {}

    /**
     * @param list<CollectedData> $collectedData
     */
    public function inject(array $collectedData, FlatteningResultCollection $collection, string $targetDirectory, int $run): ScopeInjectionResultCollection
    {
        $targetDirectory = Path::join($targetDirectory, (string) $run);

        $this->filesystem->mkdir($targetDirectory);

        $contextBeforeBlockByBlock = [];
        $macros = [];

        foreach ($collectedData as $data) {
            if ($data->collecterType === MacroCollector::class) {
                $macros[$data->filePath] = $data->data['macros'];
            } elseif ($data->collecterType === BlockContextCollector::class) {
                $phpDocNode = $this->phpDocParser->parseTagValue(
                    new TokenIterator($this->lexer->tokenize($data->data['context'])),
                    '@var',
                );

                if ( ! $phpDocNode instanceof VarTagValueNode) {
                    throw new LogicException('Invalid @var tag.');
                }

                $context = $phpDocNode->type;

                if ( ! $context instanceof ArrayShapeNode) {
                    $context = ArrayShapeNode::createSealed([]);
                }

                $contextBeforeBlockByBlock[$data->data['blockName']][] = [
                    'blockName' => $data->data['blockName'],
                    'sourceLocation' => SourceLocation::decode($data->data['sourceLocation']),
                    'context' => $context,
                    'parent' => $data->data['parent'],
                    'relatedBlockName' => $data->data['relatedBlockName'],
                    'relatedParent' => $data->data['relatedParent'],
                ];
            }
        }

        $contextBeforeBlock = [];
        foreach ($contextBeforeBlockByBlock as $contexts) {
            foreach ($contexts as $context) {
                $contextBeforeBlock[] = $this->getRecursiveContext($context, $contextBeforeBlockByBlock);
            }
        }

        $results = new ScopeInjectionResultCollection();
        foreach ($collection as $flatteningResult) {
            $contextBeforeBlockRelatedToTemplate = array_values(array_filter(
                $contextBeforeBlock,
                fn($contextBeforeBlock) => $contextBeforeBlock['sourceLocation']->contains($flatteningResult->twigFilePath),
            ));
            $stmts = $this->applyVisitors(
                $this->phpParser->parseFile($flatteningResult->phpFile),
                new NameResolver(),
                new InjectContextVisitor(
                    $contextBeforeBlockRelatedToTemplate,
                    $this->arrayShapeMerger,
                ),
                ...isset($macros[$flatteningResult->phpFile]) ? [new InjectMacroVisitor($macros[$flatteningResult->phpFile])] : [],
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
                $flatteningResult->twigFilePath,
                $phpFile,
                $visitor->getMapping(),
            ));
        }

        return $results;
    }

    /**
     * @param array{
     *     blockName: string|null,
     *     sourceLocation: SourceLocation,
     *     context: \PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode,
     *     parent: bool,
     *     relatedBlockName: string|null,
     *     relatedParent: bool,
     *  } $context
     * @param array<array<array{
     *     blockName: string|null,
     *     sourceLocation: SourceLocation,
     *     context: \PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode,
     *     parent: bool,
     *     relatedBlockName: string|null,
     *     relatedParent: bool,
     * }>> $contextBeforeBlockByBlock
     *
     * @return array{
     *     blockName: string|null,
     *     sourceLocation: SourceLocation,
     *     context: \PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode,
     *     parent: bool,
     *     relatedBlockName: string|null,
     *     relatedParent: bool,
     * }
     */
    private function getRecursiveContext(array $context, array $contextBeforeBlockByBlock): array
    {
        if ($context['relatedBlockName'] === null) {
            return $context;
        }

        if ( ! isset($contextBeforeBlockByBlock[$context['relatedBlockName']])) {
            return $context;
        }

        $parent = null;
        foreach ($contextBeforeBlockByBlock[$context['relatedBlockName']] as $parentContext) {
            if ($context['relatedParent'] !== $parentContext['relatedParent']) {
                continue;
            }

            if ($context['sourceLocation']->last()->fileName !== $parentContext['sourceLocation']->last()->fileName) {
                continue;
            }

            $parentContext = $this->getRecursiveContext($parentContext, $contextBeforeBlockByBlock);

            if ($parent === null) {
                $parent = $parentContext['context'];
            } else {
                $parent = $this->arrayShapeMerger->merge($parent, $parentContext['context']);
            }
        }

        if ($parent === null) {
            return $context;
        }

        $context['context'] = $this->arrayShapeMerger->merge($context['context'], $parent, true);

        return $context;
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

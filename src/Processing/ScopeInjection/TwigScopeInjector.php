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

        $contextBeforeBlock = [];
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

                $contextBeforeBlock[] = [
                    'blockName' => $data->data['blockName'],
                    'sourceLocation' => SourceLocation::decode($data->data['sourceLocation']),
                    'context' => $context,
                    'parent' => $data->data['parent'],
                ];
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

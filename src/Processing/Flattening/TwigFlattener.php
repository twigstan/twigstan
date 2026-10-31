<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use TwigStan\PHP\PrettyPrinter;
use TwigStan\PHP\StrictPhpParser;
use TwigStan\Processing\Compilation\CompilationResultCollection;
use TwigStan\Processing\Flattening\PhpVisitor\BlockMethodFindingVisitor;
use TwigStan\Processing\Flattening\PhpVisitor\InjectBlockMethodsFromParentVisitor;
use TwigStan\Processing\Flattening\PhpVisitor\InjectPropertiesFromParentVisitor;
use TwigStan\Processing\Flattening\PhpVisitor\InlineParentTemplateVisitor;
use TwigStan\Processing\Flattening\PhpVisitor\MainMethodFinderVisitor;
use TwigStan\Twig\Metadata\MetadataRegistry;
use TwigStan\Twig\SourceLocation;

final class TwigFlattener
{
    /**
     * @var array<string, list<string>>
     */
    private array $cachedBlocks = [];

    public function __construct(
        private readonly PrettyPrinter $prettyPrinter,
        private readonly Filesystem $filesystem,
        private readonly MetadataRegistry $metadataRegistry,
        private readonly StrictPhpParser $phpParser,
    ) {}

    /**
     * Flattens the template by inlining the parent template(s) and block(s).
     */
    public function flatten(CompilationResultCollection $collection, string $targetDirectory, int $run): FlatteningResultCollection
    {
        $targetDirectory = Path::join($targetDirectory, (string) $run);

        $this->filesystem->mkdir($targetDirectory);

        $results = new FlatteningResultCollection();
        $this->cachedBlocks = [];
        foreach ($collection as $compilationResult) {
            $metadata = $this->metadataRegistry->getMetadata($compilationResult->twigFilePath);

            if ($metadata->hasResolvableParents()) {
                // A dynamic parent, such as `{% extends boxed ? 'boxed.twig' : 'wide.twig' %}`, can be any of them.
                $parents = array_values(array_unique(array_filter(
                    $metadata->parents,
                    fn($parent) => ! str_contains($parent, '$'),
                )));

                $sourceLocation = new SourceLocation(
                    $metadata->filePath,
                    $metadata->parentLineNumber ?? 0,
                );

                $parentStmts = [];
                $parentBlockMethods = [];
                $mainMethodFinderVisitors = [];
                foreach ($parents as $parent) {
                    $blocksNeededFromParent = array_diff(
                        $this->getRecursiveBlocks($parent),
                        $metadata->blocks,
                    );

                    $blocksNeededFromParent = array_combine(
                        array_map(
                            fn($block) => 'block_' . $block,
                            $blocksNeededFromParent,
                        ),
                        $blocksNeededFromParent,
                    );

                    foreach ($metadata->parentBlocks as $parentBlock) {
                        $blocksNeededFromParent['parent_block_' . $parentBlock] = $parentBlock;
                    }

                    // Inline the body of the `main` function from the parent template
                    if ( ! $results->hasTwigFileName($parent)) {
                        throw new RuntimeException(sprintf('Parent template %s not found in mapping.', $parent));
                    }

                    $parentTransformResult = $results->getByTwigFileName($parent);
                    $phpAst = $this->phpParser->parseFile($parentTransformResult->phpFile);
                    // Find `main` method in parent template
                    $mainMethodFinderVisitor = new MainMethodFinderVisitor();
                    // Find block methods in parent template
                    $blockMethodFinderVisitor = new BlockMethodFindingVisitor();
                    $traverser = new NodeTraverser();
                    $traverser->addVisitor($mainMethodFinderVisitor);
                    $traverser->addVisitor($blockMethodFinderVisitor);
                    $traverser->traverse($phpAst);

                    $parentStmts[] = $mainMethodFinderVisitor->stmts;
                    $mainMethodFinderVisitors[] = $mainMethodFinderVisitor;

                    foreach ($blocksNeededFromParent as $alias => $block) {
                        if ( ! isset($blockMethodFinderVisitor->blocks[$block])) {
                            continue;
                        }

                        $parentBlockMethods[$alias][] = $blockMethodFinderVisitor->blocks[$block];
                    }
                }

                // A block that more parents define is declared once, with the body of each parent as an alternative.
                $blockMethods = [];
                foreach ($parentBlockMethods as $alias => $methods) {
                    $method = $methods[0];
                    $method->stmts = InlineParentTemplateVisitor::alternatives(array_map(
                        fn(Node\Stmt\ClassMethod $method) => $method->stmts ?? [],
                        $methods,
                    ));
                    $blockMethods[$alias] = [$method, $sourceLocation];
                }

                // Inline the parent statements into the child template
                $phpAst = $this->phpParser->parseFile($compilationResult->phpFile);
                $traverser = new NodeTraverser();
                $traverser->addVisitor(new InlineParentTemplateVisitor(InlineParentTemplateVisitor::alternatives($parentStmts)));
                $traverser->addVisitor(new InjectBlockMethodsFromParentVisitor($blockMethods));
                $phpAst = $traverser->traverse($phpAst);

                foreach ($mainMethodFinderVisitors as $mainMethodFinderVisitor) {
                    $traverser = new NodeTraverser();
                    $traverser->addVisitor(new InjectPropertiesFromParentVisitor($mainMethodFinderVisitor->properties, $mainMethodFinderVisitor->constructorStmts));
                    $phpAst = $traverser->traverse($phpAst);
                }

                $phpSource = $this->prettyPrinter->prettyPrintFile($phpAst);

                $phpFile = Path::join($targetDirectory, basename($compilationResult->phpFile));

                $this->filesystem->dumpFile(
                    $phpFile,
                    $phpSource,
                );

                $results = $results->with(new FlatteningResult(
                    $compilationResult->twigFilePath,
                    $phpFile,
                ));

                continue;
            }

            $this->filesystem->copy(
                $compilationResult->phpFile,
                Path::join($targetDirectory, basename($compilationResult->phpFile)),
            );

            $results = $results->with(
                new FlatteningResult(
                    $compilationResult->twigFilePath,
                    Path::join(
                        $targetDirectory,
                        basename($compilationResult->phpFile),
                    ),
                ),
            );
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function getRecursiveBlocks(string $parent): array
    {
        if ( ! isset($this->cachedBlocks[$parent])) {
            $parentMetadata = $this->metadataRegistry->getMetadata($parent);

            $blocks = $parentMetadata->blocks;
            foreach ($parentMetadata->parents as $grandParent) {
                if (str_contains($grandParent, '$')) {
                    continue;
                }

                $blocks = [...$blocks, ...$this->getRecursiveBlocks($grandParent)];
            }

            $this->cachedBlocks[$parent] = array_values(array_unique($blocks));
        }

        return $this->cachedBlocks[$parent];
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Benchmarks;

use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;
use Tally\Attribute\ExpectedFor;
use Tally\ScratchDirectory;
use TwigStan\Application\TwigStanAnalysisResult;
use TwigStan\Benchmarks\Support\Fixtures;
use TwigStan\Benchmarks\Support\TwigStanProject;

require_once __DIR__ . '/support/Fixtures.php';
require_once __DIR__ . '/support/TwigStanProject.php';

/**
 * The whole analysis of a project, as `twigstan analyze` runs it. TwigStan runs PHPStan in other processes, which are
 * not counted, so this counts what TwigStan does itself: finding the templates and their dependencies, compiling,
 * flattening and injecting the context, merging the contexts of the render points, and mapping the errors back to
 * the templates. The project is analysed once before, so that PHPStan's result cache is warm, as when TwigStan runs
 * again after a change.
 */
final class AnalyzeBench
{
    private TwigStanProject $project;

    public function __construct(
        private readonly ScratchDirectory $scratch,
    ) {}

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function projects(): iterable
    {
        yield 'bug-317-nested-loops' => [Fixtures::bug317()];
        yield 'recursive-include' => [Fixtures::recursiveInclude(false)];
        yield 'recursive-include-next-level' => [Fixtures::recursiveInclude(true)];
        yield 'recursive-blocks' => [Fixtures::recursiveBlocks()];
        yield 'huge-template' => [Fixtures::hugeTemplate(40)];
        yield 'huge-context' => [Fixtures::hugeContext(200)];
        yield 'many-render-points' => [Fixtures::manyRenderPoints(100)];
        yield 'many-templates' => [Fixtures::manyTemplates(50)];
        yield 'many-errors' => [Fixtures::manyErrors(20, 30)];
    }

    /**
     * @param array<string, string> $files
     */
    #[Before]
    public function analyzeOnce(array $files): void
    {
        $this->project = new TwigStanProject($this->scratch->path(), $files);
        $this->project->analyze();
    }

    /**
     * @param array<string, string> $files
     */
    #[DataProvider('projects')]
    #[ExpectedFor('bug-317-nested-loops', instructions: '392.1M', cycles: '446.2M', memory: '1.721MiB')]
    #[ExpectedFor('huge-context', instructions: '2.587B', cycles: '3.179B', memory: '19.75MiB')]
    #[ExpectedFor('huge-template', instructions: '7.512B', cycles: '9.441B', memory: '48.28MiB')]
    #[ExpectedFor('many-errors', instructions: '4.309B', cycles: '4.851B', memory: '2.357MiB')]
    #[ExpectedFor('many-render-points', instructions: '237.5M', cycles: '275.4M', memory: '1018KiB')]
    #[ExpectedFor('many-templates', instructions: '5.929B', cycles: '6.790B', memory: '4.616MiB')]
    #[ExpectedFor('recursive-blocks', instructions: '150.1M', cycles: '176.2M', memory: '989.6KiB')]
    #[ExpectedFor('recursive-include', instructions: '161.1M', cycles: '188.0M', memory: '488.1KiB')]
    #[ExpectedFor('recursive-include-next-level', instructions: '162.0M', cycles: '189.8M', memory: '490.4KiB')]
    public function benchAnalyze(array $files): TwigStanAnalysisResult
    {
        return $this->project->analyze();
    }
}

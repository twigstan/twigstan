<?php

declare(strict_types=1);

namespace TwigStan\Benchmarks;

use Tally\Attribute\Before;
use Tally\Attribute\DataProvider;
use Tally\ScratchDirectory;
use TwigStan\Benchmarks\Support\Fixtures;
use TwigStan\Benchmarks\Support\TwigStanProject;

require_once __DIR__ . '/support/Fixtures.php';
require_once __DIR__ . '/support/TwigStanProject.php';

/**
 * PHPStan analysing the templates as TwigStan compiled them, with TwigStan's extensions, as in the last PHPStan
 * process of `twigstan analyze`. This is where most of the time of an analysis goes, and it grows with what TwigStan
 * generates: the size of the context types, the code of the inlined parents, and the extensions that type the calls.
 */
final class TemplateAnalysisBench
{
    /**
     * @var callable(): int
     */
    private $analyse;

    public function __construct(
        private readonly ScratchDirectory $scratch,
    ) {}

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function projects(): iterable
    {
        yield 'bug-317-nested-loops' => [Fixtures::bug317()];
        yield 'nested-loops-6-levels' => [Fixtures::nestedLoops(6, 30)];
        yield 'huge-template' => [Fixtures::hugeTemplate(40)];
        yield 'huge-context' => [Fixtures::hugeContext(200)];
        yield 'many-render-points' => [Fixtures::manyRenderPoints(100)];
    }

    /**
     * @param array<string, string> $files
     */
    #[Before]
    public function compile(array $files): void
    {
        $project = new TwigStanProject($this->scratch->path(), $files);
        $project->analyze();
        $this->analyse = $project->templateAnalyser('warmup.twig');
    }

    /**
     * @param array<string, string> $files
     */
    #[DataProvider('projects')]
    public function benchAnalyse(array $files): int
    {
        return ($this->analyse)();
    }
}

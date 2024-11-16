<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Collectors\Collector;
use TwigStan\Twig\SourceLocation;

/**
 * @phpstan-type TemplateData = array{
 *      sourceLocation: SourceLocation,
 *      template: string,
 *      context: string,
 *  }
 *
 * @template-covariant TNodeType of Node
 * @extends Collector<TNodeType, non-empty-list<TemplateData>>
 */
interface TemplateContextCollector extends Collector, ExportingCollector {}

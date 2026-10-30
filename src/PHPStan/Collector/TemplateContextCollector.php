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
 *      macro?: string,
 *      context: string,
 *  }
 *
 * When macro is set, the context contains the arguments that the macro of the template is called with.
 *
 * @template-covariant TNodeType of Node
 * @extends Collector<TNodeType, non-empty-list<TemplateData>>
 */
interface TemplateContextCollector extends Collector, ExportingCollector {}

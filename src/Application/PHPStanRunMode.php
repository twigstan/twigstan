<?php

declare(strict_types=1);

namespace TwigStan\Application;

enum PHPStanRunMode: string
{
    case CollectPhpRenderPoints = 'collect-php-render-points';
    case CollectTwigRenderPoints = 'collect-twig-render-points';
    case AnalyzeTwigTemplates = 'analyze-twig-templates';
}

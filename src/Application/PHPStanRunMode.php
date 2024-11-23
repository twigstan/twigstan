<?php

declare(strict_types=1);

namespace TwigStan\Application;

enum PHPStanRunMode: string
{
    case CollectPhpRenderPoints = 'collect-php-render-points';
    case CollectTwigBlockContexts = 'collect-twig-block-contexts';
    case AnalyzeTwigTemplates = 'analyze-twig-templates';
}

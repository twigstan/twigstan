<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RenderPoints;

use Attribute;
use Symfony\Component\Routing\Attribute\Route;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class CustomRoute extends Route {}

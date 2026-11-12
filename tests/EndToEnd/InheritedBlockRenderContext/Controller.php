<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\InheritedBlockRenderContext;

use Twig\Environment;

final class Controller
{
    public function detail(Environment $environment, DetailPage $page): string
    {
        return $environment->render('EndToEnd/InheritedBlockRenderContext/detail.twig', ['page' => $page]);
    }

    public function layout(Environment $environment, Page $page): string
    {
        return $environment->render('EndToEnd/InheritedBlockRenderContext/layout.twig', ['page' => $page]);
    }
}

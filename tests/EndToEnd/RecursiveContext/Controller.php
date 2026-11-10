<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\RecursiveContext;

use Twig\Environment;

final class Controller
{
    /**
     * @param list<TreeNode> $items
     */
    public function index(Environment $twig, array $items, mixed $dynamic): string
    {
        return $twig->render('EndToEnd/RecursiveContext/list.twig', [
            'known' => 42,
            'items' => $items,
            'dynamic' => $dynamic,
            'metadata' => array_fill(0, 90, null),
        ]);
    }
}

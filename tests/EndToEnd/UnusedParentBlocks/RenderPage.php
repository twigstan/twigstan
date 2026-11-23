<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\UnusedParentBlocks;

use Twig\Environment;

final class RenderPage
{
    public function renderChild(Environment $twig, string $message): string
    {
        return $twig->render('EndToEnd/UnusedParentBlocks/child.twig', ['message' => $message]);
    }

    public function renderParent(Environment $twig, string $message): string
    {
        return $twig->render('EndToEnd/UnusedParentBlocks/layout.twig', ['message' => $message]);
    }

    public function renderChildWithParent(Environment $twig, string $message): string
    {
        return $twig->render('EndToEnd/UnusedParentBlocks/child_with_parent.twig', ['message' => $message]);
    }
}

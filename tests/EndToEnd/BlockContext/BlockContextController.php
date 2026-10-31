<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\BlockContext;

use Twig\Environment;

final class BlockContextController
{
    public function dynamicExtends(Environment $environment, bool $boxed): string
    {
        return $environment->render('EndToEnd/BlockContext/dynamic_extends.twig', [
            'boxed' => $boxed,
            'name' => 'Ruud',
        ]);
    }

    public function blockFunction(Environment $environment): string
    {
        return $environment->render('EndToEnd/BlockContext/block_function.twig', [
            'name' => 'Ruud',
        ]);
    }

    public function parentCall(Environment $environment): string
    {
        return $environment->render('EndToEnd/BlockContext/parent_call.twig', [
            'name' => 'Ruud',
        ]);
    }

    public function embedInBlock(Environment $environment): string
    {
        return $environment->render('EndToEnd/BlockContext/embed_in_block.twig', [
            'name' => 'Ruud',
        ]);
    }

    public function embedInChild(Environment $environment): string
    {
        return $environment->render('EndToEnd/BlockContext/embed_in_child.twig', [
            'name' => 'Ruud',
        ]);
    }
}

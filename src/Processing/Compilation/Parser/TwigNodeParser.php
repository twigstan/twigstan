<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\Parser;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Node\ModuleNode;
use TwigStan\Twig\TwigFileCanonicalizer;

final readonly class TwigNodeParser
{
    public function __construct(
        private Environment $twig,
        private TwigFileCanonicalizer $twigFileCanonicalizer,
    ) {}

    /**
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function parse(ModuleNode | string $template): ModuleNode
    {
        if ($template instanceof ModuleNode) {
            return $template;
        }

        $template = $this->twigFileCanonicalizer->canonicalize($template);

        $source = $this->twig->getLoader()->getSourceContext($template);

        $stream = $this->twig->tokenize($source);

        $ast = $this->twig->parse($stream);

        return $ast;
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\Parser;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Node\ModuleNode;

final readonly class TwigNodeParser
{
    public function __construct(
        private Environment $twig,
    ) {}

    /**
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function parse(string | ModuleNode $template): ModuleNode
    {
        if ($template instanceof ModuleNode) {
            return $template;
        }

        $source = $this->twig->getLoader()->getSourceContext($template);

        $stream = $this->twig->tokenize($source);

        $ast = $this->twig->parse($stream);

        return $ast;
    }
}

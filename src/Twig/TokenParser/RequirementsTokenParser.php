<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenParser\TypesTokenParser;

final class RequirementsTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): RequirementsNode
    {
        $typesParser = new TypesTokenParser();
        $typesParser->setParser($this->parser);

        $types = $typesParser->parse($token);

        return new RequirementsNode($types->getAttribute('mapping'), $token->getLine());
    }

    public function getTag(): string
    {
        return 'requirements';
    }
}

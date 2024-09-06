<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class DumpTypeTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): DumpTypeNode
    {
        $stream = $this->parser->getStream();

        $expr = null;
        if (! $stream->test(Token::BLOCK_END_TYPE)) {
            $expr = $this->parser->getExpressionParser()->parseExpression();
        }

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new DumpTypeNode($expr, $token->getLine());
    }

    public function getTag(): string
    {
        return 'dump_type';
    }

}

<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class AssertTypeTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): AssertTypeNode
    {
        $stream = $this->parser->getStream();

        $name = $this->parser->getExpressionParser()->parseExpression();

        $expectedType = $stream->expect(Token::STRING_TYPE);

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new AssertTypeNode($name, $expectedType->getValue(), $token->getLine());
    }

    public function getTag(): string
    {
        return 'assert_type';
    }
}

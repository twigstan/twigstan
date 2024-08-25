<?php

declare(strict_types=1);

namespace TwigStan\Testing;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class ExpectErrorTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): ExpectErrorNode
    {
        $line = $this->parser->getExpressionParser()->parseExpression();
        $error = $this->parser->getExpressionParser()->parseExpression();

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new ExpectErrorNode($line, $error, $token->getLine());
    }

    public function getTag(): string
    {
        return 'expect_error';
    }

}

<?php

declare(strict_types=1);

namespace TwigStan\Testing;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class ExpectErrorTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): ExpectErrorNode
    {
        $stream = $this->parser->getStream();
        $arg1 = $this->parser->getExpressionParser()->parseExpression();
        $arg2 = $this->parser->getExpressionParser()->parseExpression();

        if ($stream->nextIf(Token::BLOCK_END_TYPE)) {
            return new ExpectErrorNode(null, $arg1->getAttribute('value'), $arg2->getAttribute('value'));
        }

        $arg3 = $this->parser->getExpressionParser()->parseExpression();

        $stream->expect(Token::BLOCK_END_TYPE);

        return new ExpectErrorNode($arg1->getAttribute('value'), $arg2->getAttribute('value'), $arg3->getAttribute('value'));
    }

    public function getTag(): string
    {
        return 'expect_error';
    }

}

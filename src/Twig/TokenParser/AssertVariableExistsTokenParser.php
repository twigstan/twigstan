<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Environment;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class AssertVariableExistsTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): AssertVariableExistsNode
    {
        $stream = $this->parser->getStream();

        // @phpstan-ignore method.notFound
        $name = Environment::VERSION_ID <= 32000 ? $this->parser->getExpressionParser()->parseExpression() : $this->parser->parseExpression();

        $certainty = $stream->expect(Token::NAME_TYPE, ['no', 'maybe', 'yes']);

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new AssertVariableExistsNode($name, $certainty->getValue(), $token->getLine());
    }

    public function getTag(): string
    {
        return 'assert_variable_exists';
    }
}

<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Node\EmptyNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class AssertTypeTokenParser extends AbstractTokenParser
{
    /**
     * @param bool $compile Whether or not to compile the PHPStan assertType call.
     */
    public function __construct(
        private readonly bool $compile = true,
    ) {}

    public function parse(Token $token): AssertTypeNode | EmptyNode
    {
        $stream = $this->parser->getStream();

        $name = $this->parser->getExpressionParser()->parseExpression();

        $expectedType = $stream->expect(Token::STRING_TYPE);

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        if ( ! $this->compile) {
            return new EmptyNode($token->getLine());
        }

        return new AssertTypeNode($name, $expectedType->getValue(), $token->getLine());
    }

    public function getTag(): string
    {
        return 'assert_type';
    }
}

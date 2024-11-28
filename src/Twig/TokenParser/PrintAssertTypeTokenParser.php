<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Node\Nodes;
use Twig\Node\PrintNode;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

final class PrintAssertTypeTokenParser extends AbstractTokenParser
{
    /**
     * @param bool $compileAssertType Whether or not to compile the PHPStan assertType call.
     */
    public function __construct(
        private readonly bool $compileAssertType = true,
    ) {}

    public function parse(Token $token): Nodes
    {
        $stream = $this->parser->getStream();

        $name = $this->parser->getExpressionParser()->parseExpression();

        $expectedType = $stream->expect(Token::STRING_TYPE);

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        $nodes = [
            new PrintNode($name, $token->getLine()),
            new TextNode("\n", $token->getLine()),
        ];

        if ($this->compileAssertType) {
            $nodes[] = new AssertTypeNode($name, $expectedType->getValue(), $token->getLine());
        }

        return new Nodes($nodes);
    }

    public function getTag(): string
    {
        return 'print_assert_type';
    }
}

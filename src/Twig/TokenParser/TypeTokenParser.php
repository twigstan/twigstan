<?php

declare(strict_types=1);

namespace TwigStan\Twig\TokenParser;

use Twig\Error\SyntaxError;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use TwigStan\Twig\Node\TypeNode;

final class TypeTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): TypeNode
    {
        $stream = $this->parser->getStream();

        $types = [];
        while (true) {
            $name = $stream->expect(Token::NAME_TYPE);
            $type = $stream->expect(Token::STRING_TYPE);

            if (array_key_exists($name->getValue(), $types)) {
                throw new SyntaxError(sprintf('Type "%s" is already defined.', $name->getValue()), $name->getLine());
            }

            $types[$name->getValue()] = $type->getValue();

            if (!$stream->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }

            if ($stream->test(Token::BLOCK_END_TYPE)) {
                break;
            }
        }

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new TypeNode($types, $token->getLine());
    }

    public function getTag(): string
    {
        return 'type';
    }
}

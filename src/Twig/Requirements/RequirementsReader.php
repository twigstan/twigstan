<?php

declare(strict_types=1);

namespace TwigStan\Twig\Requirements;

use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use TwigStan\PHP\PhpDocToPhpStanTypeResolver;
use TwigStan\PHPStan\Type\RequirementsConstantArrayType;
use TwigStan\Twig\Parser\TwigNodeParser;

final readonly class RequirementsReader
{
    public function __construct(
        private TwigNodeParser $twigNodeParser,
        private PhpDocToPhpStanTypeResolver $phpDocToPhpStanTypeResolver,
    ) {}

    /**
     * @throws RequirementsNotFoundException
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function read(string $filename): RequirementsConstantArrayType
    {
        $moduleNode = $this->twigNodeParser->parse($filename);

        if (! $moduleNode->hasAttribute('requirements')) {
            throw RequirementsNotFoundException::create();
        }

        $requirements = $moduleNode->getAttribute('requirements');

        return RequirementsConstantArrayType::createFrom($this->phpDocToPhpStanTypeResolver->resolveArray($requirements));
    }
}

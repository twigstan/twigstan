<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use InvalidArgumentException;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\FilesystemLoader;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TokenParser\AbstractTokenParser;
use TwigStan\Twig\Loader\AbsolutePathLoader;

final readonly class TwigFactory
{
    /**
     * @param list<AbstractTokenParser> $tokenParsers
     * @param list<NodeVisitorInterface> $nodeVisitors
     */
    public function __construct(
        private array $tokenParsers,
        private array $nodeVisitors,
        private ?string $environmentLoader = null,
    ) {}

    public function create(): Environment
    {
        $environmentLoader = $this->environmentLoader;

        if ($environmentLoader === null) {
            return new Environment(new ArrayLoader());
        }

        $twig = include $environmentLoader;

        // Symfony automatically sets these variables
        // We don't want this, as it might affect our AnalyzeCommand later.
        putenv('SHELL_VERBOSITY=0');
        unset($_ENV['SHELL_VERBOSITY']);
        unset($_SERVER['SHELL_VERBOSITY']);

        if (!$twig instanceof Environment) {
            throw new InvalidArgumentException(sprintf('Environment loader "%s" must return an instance of %s, got: %s. ', $this->environmentLoader, Environment::class, get_debug_type($twig)));
        }

        $loader = $twig->getLoader();

        if (!$loader instanceof FilesystemLoader) {
            throw new InvalidArgumentException(sprintf('Loader must be an instance of %s', FilesystemLoader::class));
        }

        $twig->setLoader(new AbsolutePathLoader($loader));

        $twig->getExtension(EscaperExtension::class)->setDefaultStrategy(false);

        foreach ($this->tokenParsers as $tokenParser) {
            $twig->addTokenParser($tokenParser);
        }

        foreach ($this->nodeVisitors as $nodeVisitor) {
            $twig->addNodeVisitor($nodeVisitor);
        }

        return $twig;
    }
}

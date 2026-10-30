<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Loader\ArrayLoader;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TokenParser\AbstractTokenParser;

final class TwigFactory
{
    private ?Environment $twig = null;

    private ?ContainerInterface $container = null;

    /**
     * @param list<AbstractTokenParser> $tokenParsers
     * @param list<NodeVisitorInterface> $nodeVisitors
     */
    public function __construct(
        private readonly array $tokenParsers,
        private readonly array $nodeVisitors,
        private readonly ?string $environmentLoader = null,
    ) {}

    public function create(): Environment
    {
        if ($this->environmentLoader === null) {
            return new Environment(new ArrayLoader());
        }

        $twig = $this->load();

        $twig->getExtension(EscaperExtension::class)->setDefaultStrategy(false);

        foreach ($this->tokenParsers as $tokenParser) {
            $twig->addTokenParser($tokenParser);
        }

        foreach ($this->nodeVisitors as $nodeVisitor) {
            $twig->addNodeVisitor($nodeVisitor);
        }

        return $twig;
    }

    /**
     * The container of the booted Symfony kernel, when the environment loader returns the kernel instead of the Twig environment.
     * The test container is preferred, because it exposes private services, such as twig.
     */
    public function getContainer(): ?ContainerInterface
    {
        if ($this->environmentLoader === null) {
            return null;
        }

        $this->load();

        return $this->container;
    }

    private function load(): Environment
    {
        if ($this->twig !== null) {
            return $this->twig;
        }

        $twig = include $this->environmentLoader;

        // Symfony automatically sets these variables
        // We don't want this, as it might affect our AnalyzeCommand later.
        putenv('SHELL_VERBOSITY=0');
        unset($_ENV['SHELL_VERBOSITY']);
        unset($_SERVER['SHELL_VERBOSITY']);

        if ($twig instanceof KernelInterface) {
            $container = $twig->getContainer();
            $testContainer = $container->has('test.service_container') ? $container->get('test.service_container') : null;
            $this->container = $testContainer instanceof ContainerInterface ? $testContainer : $container;
            $twig = $this->container->get('twig');
        }

        if ( ! $twig instanceof Environment) {
            throw new InvalidArgumentException(sprintf('Environment loader "%s" must return an instance of %s or %s, got: %s.', $this->environmentLoader, Environment::class, KernelInterface::class, get_debug_type($twig)));
        }

        return $this->twig = $twig;
    }
}

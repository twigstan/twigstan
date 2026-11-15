<?php

declare(strict_types=1);

namespace TwigStan\Twig\Metadata;

use RuntimeException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Node\BodyNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\Expression\Ternary\ConditionalTernary;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\ImportNode;
use Twig\Node\Node;
use Twig\Source;
use TwigStan\Processing\Compilation\Parser\TwigNodeParser;
use TwigStan\Twig\Node\NodeFinder;

final readonly class MetadataAnalyzer
{
    public function __construct(
        private Environment $twig,
        private TwigNodeParser $twigNodeParser,
        private NodeFinder $nodeFinder,
    ) {}

    public function getMetadata(string $template): Metadata
    {
        // Twig reports deprecated features while parsing, such as deprecated filters.
        $deprecations = [];
        set_error_handler(
            function (int $level, string $message) use (&$deprecations): bool {
                $deprecations[] = $message;

                return true;
            },
            E_USER_DEPRECATED,
        );

        try {
            $template = $this->twigNodeParser->parse($template);

            // Twig reports some syntax errors only when compiling, such as missing arguments.
            $this->twig->compile($template);
        } catch (SyntaxError $error) {
            $source = $error->getSourceContext();

            if ($source === null) {
                throw $error;
            }

            return new Metadata(
                $source->getName(),
                $this->twig->getTemplateClass($source->getName()),
                $source->getPath(),
                parentLineNumber: null,
                parents: [],
                traits: [],
                blocks: [],
                parentBlocks: [],
                dependencies: [],
                isAbstract: false,
                syntaxError: ['line' => $error->getTemplateLine(), 'message' => $error->getRawMessage()],
            );
        } finally {
            restore_error_handler();
        }

        if ($template->getSourceContext() === null) {
            throw new RuntimeException('Template has no source context.');
        }

        $parentLineNumber = null;
        $parents = [];
        $loaderErrors = [];

        if ($template->hasNode('parent')) {
            $parentLineNumber = $template->getNode('parent')->getTemplateLine();
            $parents = $this->resolve($this->getStringsFromExpression($template->getNode('parent')), $parentLineNumber, $loaderErrors);
        }

        $traits = [];
        foreach ($template->getNode('traits') as $trait) {
            $targets = [];
            foreach ($trait->getNode('targets') as $blockName => $target) {
                $alias = $target->getAttribute('value');

                if ( ! is_string($alias)) {
                    throw new RuntimeException(sprintf('Expected the alias of block "%s" to be a string.', $blockName));
                }

                $targets[$blockName] = $alias;
            }

            $name = $trait->getNode('template')->getAttribute('value');

            if ( ! is_string($name)) {
                throw new RuntimeException('Expected the name of the used template to be a string.');
            }

            $traits[] = [
                'name' => $name,
                'targets' => $targets,
            ];
        }

        $blocks = [];
        $parentBlocks = [];

        foreach ($template->getNode('blocks') as $block) {
            if ( ! $block instanceof BodyNode) {
                throw new RuntimeException(sprintf('Node is expected to be of type %s, got %s.', BodyNode::class, $block::class));
            }

            $blockName = $block->getNode('0')->getAttribute('name');
            $blocks[] = $blockName;

            $parentExpression = $this->nodeFinder->findFirstInstanceOf($block, ParentExpression::class);

            if ($parentExpression !== null) {
                $parentBlocks[] = $blockName;
            }
        }

        // Templates whose compiled classes are loaded, so they are needed to analyze this template.
        $dependencies = [];
        foreach ([$template->getNode('body'), ...$template->getNode('blocks'), ...$template->getNode('macros')] as $node) {
            foreach ($this->nodeFinder->findInstanceOf($node, ImportNode::class) as $importNode) {
                $dependencies = [...$dependencies, ...$this->resolve($this->getStringsFromExpression($importNode->getNode('expr')), $importNode->getTemplateLine(), $loaderErrors)];
            }

            foreach ($this->nodeFinder->findInstanceOf($node, BlockReferenceExpression::class) as $blockReference) {
                if ( ! $blockReference->hasNode('template')) {
                    continue;
                }

                $dependencies = [...$dependencies, ...$this->resolve($this->getStringsFromExpression($blockReference->getNode('template')), $blockReference->getTemplateLine(), $loaderErrors)];
            }
        }

        return new Metadata(
            $template->getSourceContext()->getName(),
            $this->twig->getTemplateClass($template->getSourceContext()->getName()),
            $template->getSourceContext()->getPath(),
            $parentLineNumber,
            $parents,
            $traits,
            $blocks,
            $parentBlocks,
            array_values(array_unique($dependencies)),
            str_contains($template->getSourceContext()->getCode(), '@twigstan-abstract'),
            $loaderErrors,
            $this->isSelfFormTheme($template),
            errors: [
                ...$this->getDeprecations($deprecations, $template->getSourceContext()),
                ...array_map(
                    fn(int $line) => ['line' => $line, 'message' => 'Call to the debug function dump().', 'identifier' => 'twig.dump'],
                    $this->getDumpCallLines($template),
                ),
            ],
        );
    }

    /**
     * Keeps the deprecations about the template, leaving out the deprecated APIs that extensions use.
     *
     * @param list<string> $messages
     *
     * @return list<array{line: int, message: string, identifier: string}>
     */
    private function getDeprecations(array $messages, Source $source): array
    {
        // Twig mentions the template by its path or by its name, such as 'in "@App/page.twig" at line 3'.
        $location = sprintf(
            '# in "?(?:%s)"?(?: at line \d+)?#',
            implode('|', array_map(
                fn(string $location) => preg_quote($location, '#'),
                array_filter([$source->getPath(), $source->getName()], fn(string $location) => $location !== ''),
            )),
        );

        $deprecations = [];
        foreach ($messages as $message) {
            if (preg_match($location, $message) !== 1) {
                continue;
            }

            // The last line is the one that triggered the deprecation, as in "previous definition at line 1, new definition at line 3".
            preg_match('#.*at line (\d+)#', $message, $line);

            $deprecations[] = [
                'line' => (int) ($line[1] ?? 0),
                'message' => (string) preg_replace($location, '', $message),
                'identifier' => 'twig.deprecated',
            ];
        }

        return $deprecations;
    }

    /**
     * @return list<int>
     */
    private function getDumpCallLines(Node $node): array
    {
        $lines = [];
        foreach ($node as $child) {
            // Macros imported as dump are macro references, not functions.
            if ($child instanceof FunctionExpression && $child->getAttribute('name') === 'dump') {
                $lines[] = $child->getTemplateLine();
            }

            $lines = [...$lines, ...$this->getDumpCallLines($child)];
        }

        return $lines;
    }

    private function isSelfFormTheme(Node $template): bool
    {
        // The node of the form_theme tag of the Twig bridge of Symfony, which is not always installed.
        foreach ($this->nodeFinder->findInstanceOf($template, 'Symfony\\Bridge\\Twig\\Node\\FormThemeNode') as $formTheme) {
            foreach ($formTheme->getNode('resources') as $resource) {
                if ($resource instanceof ContextVariable && $resource->getAttribute('name') === '_self') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Returns the paths of the templates, leaving out the templates that Twig cannot load, which are added to $loaderErrors.
     *
     * @param list<string> $names
     * @param list<array{name: string, line: int, message: string}> $loaderErrors
     *
     * @return list<string>
     */
    private function resolve(array $names, int $line, array &$loaderErrors): array
    {
        $paths = [];
        foreach ($names as $name) {
            // Names that are only known at runtime
            if (str_contains($name, '$')) {
                $paths[] = $name;

                continue;
            }

            try {
                $paths[] = $this->twig->getLoader()->getSourceContext($name)->getPath();
            } catch (LoaderError $error) {
                // The directories that Twig looked into are left out, they are long and depend on the machine.
                $loaderErrors[] = ['name' => $name, 'line' => $line, 'message' => (string) preg_replace('# \(looked into: .*\)#s', '', $error->getRawMessage())];
            }
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function getStringsFromExpression(Node $node): array
    {
        if ($node instanceof ConstantExpression) {
            return [$node->getAttribute('value')];
        }

        if ($node instanceof ArrayExpression) {
            $strings = [];
            foreach ($node->getKeyValuePairs() as $pair) {
                $strings = [...$strings, ...$this->getStringsFromExpression($pair['value'])];
            }

            return $strings;
        }

        if ($node instanceof ConditionalTernary) {
            return [
                ...$this->getStringsFromExpression($node->getNode('left')),
                ...$this->getStringsFromExpression($node->getNode('right')),
            ];
        }

        if ($node instanceof ConcatBinary) {
            if ($node->getNode('left') instanceof ConstantExpression) {
                return array_map(
                    fn(string $template) => $node->getNode('left')->getAttribute('value') . $template,
                    $this->getStringsFromExpression($node->getNode('right')),
                );
            }

            if ($node->getNode('right') instanceof ConstantExpression) {
                return array_map(
                    fn(string $template) => $template . $node->getNode('right')->getAttribute('value'),
                    $this->getStringsFromExpression($node->getNode('left')),
                );
            }
        }

        // The parent is only known at runtime, such as a variable or a method call.
        return [];
    }
}

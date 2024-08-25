<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPStan\ShouldNotHappenException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Node\ModuleNode;
use TwigStan\PHP\PhpToTemplateLinesNodeVisitor;
use TwigStan\PHP\PrettyPrinter;
use TwigStan\Twig\Parser\TwigNodeParser;
use TwigStan\Twig\Transforming\NodeTransformer\DelegatingTwigNodeTransformer;

final readonly class TwigTransformer
{
    private Parser $phpParser;

    public function __construct(
        private TwigNodeParser $twigNodeParser,
        private DelegatingTwigNodeTransformer $nodeTransformer,
        private TransformScopeFactory $transformScopeFactory,
        private PrettyPrinter $prettyPrinter,
        private Filesystem $filesystem,
    ) {
        $this->phpParser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    }

    public function transform(string|ModuleNode $template, string $targetDirectory): TransformResult
    {
        if (is_string($template)) {
            $template = $this->twigNodeParser->parse($template);
        }

        if ($template->getSourceContext() === null) {
            throw new ShouldNotHappenException('Template does not have a source context');
        }

        $twigFile = $template->getSourceContext()->getPath();

        $twigNodes = $this->nodeTransformer->transform(
            $template,
            $this->transformScopeFactory->create(),
        );

        $phpSource = $this->prettyPrinter->prettyPrintFile($twigNodes);

        $phpFile = Path::join($targetDirectory, sprintf(
            '%s.%s.php',
            basename($twigFile),
            hash('crc32', $twigFile),
        ));

        $this->filesystem->dumpFile(
            $phpFile,
            $phpSource,
        );

        // This is a bit inefficient, maybe we can make this smarter
        $stmts = $this->phpParser->parse($phpSource);

        $visitor = new PhpToTemplateLinesNodeVisitor();

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($stmts);

        return new TransformResult(
            $twigFile,
            $phpFile,
            $visitor->getMapping(),
        );
    }
}

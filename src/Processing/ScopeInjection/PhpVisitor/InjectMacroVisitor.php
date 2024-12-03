<?php

declare(strict_types=1);

namespace TwigStan\Processing\ScopeInjection\PhpVisitor;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class InjectMacroVisitor extends NodeVisitorAbstract
{
    public function __construct(
        private readonly string $type,
    ) {}

    public function enterNode(Node $node): ?Node
    {
        if ( ! $node instanceof Node\Stmt\Property) {
            return null;
        }

        if ( ! isset($node->props[0])) {
            return null;
        }

        if ($node->props[0]->name->name !== 'macros') {
            return null;
        }

        $node->setDocComment(
            new Doc(
                sprintf(
                    <<<'DOC'
                        /**
                         * @var %s
                         * @phpstan-ignore property.defaultValue
                         */
                        DOC,
                    $this->type,
                ),
            ),
        );

        return $node;
    }
}

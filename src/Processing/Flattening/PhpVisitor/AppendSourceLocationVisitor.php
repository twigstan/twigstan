<?php

declare(strict_types=1);

namespace TwigStan\Processing\Flattening\PhpVisitor;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use TwigStan\Twig\CommentHelper;
use TwigStan\Twig\SourceLocation;

final class AppendSourceLocationVisitor extends NodeVisitorAbstract
{
    public function __construct(private SourceLocation $sourceLocation) {}

    public function enterNode(Node $node): ?Node
    {
        if (!$node instanceof Stmt) {
            return null;
        }

        if ($node->getComments() === []) {
            return null;
        }

        $comments = [];
        foreach ($node->getComments() as $comment) {
            $sourceLocation = CommentHelper::getSourceLocationFromComment($comment->getText());
            if ($sourceLocation === null) {
                $comments[] = $comment;
                continue;
            }

            $sourceLocation = SourceLocation::append($sourceLocation, $this->sourceLocation);

            $comments[] = new Comment(sprintf("// line %s", $sourceLocation));
        }

        $node->setAttribute('comments', $comments);

        return $node;
    }
}

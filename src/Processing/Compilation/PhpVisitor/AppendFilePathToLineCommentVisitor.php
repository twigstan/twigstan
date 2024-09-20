<?php

declare(strict_types=1);

namespace TwigStan\Processing\Compilation\PhpVisitor;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use TwigStan\Twig\SourceLocation;

final class AppendFilePathToLineCommentVisitor extends NodeVisitorAbstract
{
    public function __construct(private string $filePath) {}

    public function enterNode(Node $node): null
    {
        if (!$node instanceof Stmt) {
            return null;
        }

        if ($node->getComments() === []) {
            return null;
        }

        $comments = [];
        foreach ($node->getComments() as $comment) {
            if (preg_match('#// line (?<line_number>\d+)+#', $comment->getText(), $matches) !== 1) {
                $comments[] = $comment;
                continue;
            }

            $line = (int) $matches['line_number'];

            $comments[] = new Comment(sprintf(
                "// line %s",
                new SourceLocation($this->filePath, $line),
            ));
        }

        $node->setAttribute('comments', $comments);

        return null;
    }
}

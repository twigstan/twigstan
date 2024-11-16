<?php

declare(strict_types=1);

namespace TwigStan\Twig;

use PhpParser\Comment;

final readonly class CommentHelper
{
    public static function getSourceLocationFromComment(string $comment): ?SourceLocation
    {
        if ( ! str_starts_with($comment, '// line ')) {
            return null;
        }

        $comment = substr($comment, 8);

        $pairs = explode(', ', $comment);

        $sourceLocation = null;
        foreach (array_reverse($pairs) as $pair) {
            [$fileName, $line] = explode(':', $pair);

            $sourceLocation = new SourceLocation(
                $fileName,
                (int) $line,
                $sourceLocation,
            );
        }

        return $sourceLocation;
    }

    /**
     * @param array<Comment> $comments
     */
    public static function getSourceLocationFromComments(array $comments): ?SourceLocation
    {
        foreach ($comments as $comment) {
            $sourceLocation = self::getSourceLocationFromComment($comment->getText());

            if ($sourceLocation !== null) {
                return $sourceLocation;
            }
        }

        return null;
    }
}

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

        if (preg_match_all('#(?<name>[@\/\w.-]+):(?<line_number>\d+)#', $comment, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        $sourceLocation = null;
        foreach (array_reverse($matches) as $match) {
            $sourceLocation = new SourceLocation(
                $match['name'],
                (int) $match['line_number'],
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

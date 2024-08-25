<?php

declare(strict_types=1);

namespace TwigStan\Twig\Node;

use Twig\Node\Expression\NameExpression;
use Twig\Node\Node;

final readonly class NodeFinder
{
    /**
     * @return list<string>
     */
    public function findUsedVariables(Node $node): array
    {
        $variables = [];

        foreach ($node as $childNode) {
            if ($childNode instanceof NameExpression) {
                $variables[] = $childNode->getAttribute('name');
                continue;
            }

            $variables = [...$variables, ...$this->findUsedVariables($childNode)];
        }

        return array_unique($variables);
    }
}

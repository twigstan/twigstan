<?php

declare(strict_types=1);

namespace TwigStan\Twig\Transforming\NodeTransformer;

use PhpParser\Node as PhpNode;
use PhpParser\Node\Stmt;
use RuntimeException;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ParentExpression;
use Twig\Node\ForLoopNode;
use Twig\Node\ImportNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Profiler\Node\EnterProfileNode;
use Twig\Profiler\Node\LeaveProfileNode;
use TwigStan\PHP\Node\TwigLineNumberNode;
use TwigStan\Twig\Node\NodeMapper;
use TwigStan\Twig\Node\RequirementsNode;
use TwigStan\Twig\Transforming\TransformScope;

final readonly class DelegatingTwigNodeTransformer
{
    private const array NODES_TO_SKIP = [
        EnterProfileNode::class,
        LeaveProfileNode::class,
        ParentExpression::class,
        ImportNode::class, // @todo fix this
        RequirementsNode::class,
        ForLoopNode::class,
    ];

    /**
     * @var array<string, TwigNodeTransformer>
     */
    private array $transformers;

    /**
     * @param list<TwigNodeTransformer> $transformers
     */
    public function __construct(array $transformers, private NodeMapper $nodeMapper)
    {
        $mapping = [];
        foreach ($transformers as $transformer) {
            $types = $transformer::getType();
            if (!is_array($types)) {
                $types = [$types];
            }

            foreach ($types as $type) {
                $mapping[$type] = $transformer;
            }
        }

        $this->transformers = $mapping;
    }

    /**
     * @return Stmt
     */
    public function transform(Node $node, TransformScope $scope): null|PhpNode\Expr|array
    {
        if (in_array($node::class, self::NODES_TO_SKIP, true)) {
            return null;
        }

        if ($node::class === Node::class) {
            return $this->flattenAndRemoveNull($this->nodeMapper->map(
                $node,
                fn($node) => $this->transform($node, $scope),
            ));
        }

        $stmts = $this->getTransformer($node)->transform($node, $scope, $this);

        if ($node instanceof AbstractExpression) {
            return $stmts;
        }

        if (!is_array($stmts)) {
            $stmts = [$stmts];
        }

        // @todo do this in each and every transformer
        if (!$node instanceof ModuleNode && $node->getTemplateLine() > 0) {
            array_unshift(
                $stmts,
                new TwigLineNumberNode($node->getTemplateLine()),
            );
        }

        return $this->flattenAndRemoveNull($stmts);
    }

    private function getTransformer(Node $node): TwigNodeTransformer
    {
        if (isset($this->transformers[$node::class])) {
            return $this->transformers[$node::class];
        }

        foreach ($this->transformers as $nodeType => $transformer) {
            if (is_a($node, $nodeType, true)) {
                return $transformer;
            }
        }

        throw new RuntimeException(sprintf('No transformer found for node of type %s', $node::class));
    }

    private function flattenAndRemoveNull(Stmt | array $stmts): array
    {
        $cleanStmts = [];
        foreach ($stmts as $stmt) {
            if ($stmt === null) {
                continue;
            }

            if (is_array($stmt)) {
                $cleanStmts = array_merge($cleanStmts, $this->flattenAndRemoveNull($stmt));
                continue;
            }

            $cleanStmts[] = $stmt;
        }

        return $cleanStmts;
    }
}

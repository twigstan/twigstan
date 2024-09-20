<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->append([__DIR__ . '/.php-cs-fixer.php']);

return (new Config())
    ->setCacheFile('.php_cs.cache')
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules(
        [
            '@PER-CS2.0' => true,
            'binary_operator_spaces' => ['default' => 'single_space'],
            'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => true],
            'single_space_around_construct' => true,
            'type_declaration_spaces' => true,
            'types_spaces' => ['space' => 'single'],
            'unary_operator_spaces' => true,
            'no_unused_imports' => true,
        ],
    )
    ->setFinder($finder);

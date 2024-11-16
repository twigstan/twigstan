<?php

declare(strict_types=1);

use TwigCsFixer\Rules\Function\IncludeFunctionRule;

$ruleset = new TwigCsFixer\Ruleset\Ruleset();

// You can start from a default standard
$ruleset->addStandard(new TwigCsFixer\Standard\TwigCsFixer());

$ruleset->removeRule(IncludeFunctionRule::class);

$ruleset->overrideRule(new TwigCsFixer\Rules\Variable\VariableNameRule(TwigCsFixer\Rules\Variable\VariableNameRule::CAMEL_CASE));

// And then add/remove/override some rules
$ruleset->overrideRule(new TwigCsFixer\Rules\Punctuation\PunctuationSpacingRule(
    [
        '}' => 1,
        '|' => 1,
    ],
    [
        '{' => 1,
        '|' => 1,
    ],
));

$ruleset->overrideRule(new TwigCsFixer\Rules\Delimiter\DelimiterSpacingRule(false));

$finder = TwigCsFixer\File\Finder::create()
    ->name('/\.twig\d?$/')
    ->in('tests');

$config = new TwigCsFixer\Config\Config();
$config->allowNonFixableRules();
$config->setRuleset($ruleset);
$config->setFinder($finder);

return $config;

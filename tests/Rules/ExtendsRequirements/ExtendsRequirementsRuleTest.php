<?php

declare(strict_types=1);

namespace TwigStan\Rules\ExtendsRequirements;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TwigStan\PHP\ExpressionToStringResolver;
use TwigStan\PHPStan\Rules\ExtendsRequirementsRule;
use TwigStan\Rules\AbstractTwigRuleTestCase;
use TwigStan\Rules\DataProviderHelper;
use TwigStan\Twig\Requirements\RequirementsReader;

/**
 * @template-extends RuleTestCase<ExtendsRequirementsRule>
 */
class ExtendsRequirementsRuleTest extends AbstractTwigRuleTestCase
{
    protected function getRule(): Rule
    {
        return new ExtendsRequirementsRule(
            self::getContainer()->getByType(RequirementsReader::class),
            self::getContainer()->getByType(ExpressionToStringResolver::class),
        );
    }

    #[DataProvider('provideCases')]
    public function testCases(string $file, array $expectedErrors): void
    {
        $this->analyse([$file], $expectedErrors);
    }

    public static function provideCases(): iterable
    {
        yield from DataProviderHelper::createCasesFromDirectory(__DIR__, '/^[^_][\w\.]*\.twig$/');
    }
}

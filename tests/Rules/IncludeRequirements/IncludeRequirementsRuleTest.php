<?php

declare(strict_types=1);

namespace TwigStan\Rules\IncludeRequirements;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TwigStan\PHP\ExpressionToStringResolver;
use TwigStan\PHPStan\Rules\IncludeRequirementsRule;
use TwigStan\Rules\AbstractTwigRuleTestCase;
use TwigStan\Rules\DataProviderHelper;
use TwigStan\Twig\Requirements\RequirementsReader;

/**
 * @template-extends RuleTestCase<IncludeRequirementsRule>
 */
class IncludeRequirementsRuleTest extends AbstractTwigRuleTestCase
{
    protected function getRule(): Rule
    {
        return new IncludeRequirementsRule(
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

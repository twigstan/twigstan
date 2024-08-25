<?php

declare(strict_types=1);

namespace TwigStan\Rules\RenderRequirements;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use TwigStan\PHPStan\Rules\RenderRequirementsRule;
use TwigStan\Rules\DataProviderHelper;
use TwigStan\Twig\Requirements\RequirementsReader;

/**
 * @template-extends RuleTestCase<RenderRequirementsRule>
 */
class RenderRequirementsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new RenderRequirementsRule(
            self::getContainer()->getByType(RequirementsReader::class),
        );
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            ...parent::getAdditionalConfigFiles(),
            __DIR__ . '/../../config.php',
        ];
    }

    #[DataProvider('provideCases')]
    public function testCases(string $file, array $expectedErrors): void
    {
        $this->analyse([$file], $expectedErrors);
    }

    public static function provideCases(): iterable
    {
        yield from DataProviderHelper::createCasesFromDirectory(__DIR__, '/^[^_][\w\.]*\.inc/');
    }
}

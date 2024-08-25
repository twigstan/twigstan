<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Type;

use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequirementsConstantArrayTypeTest extends TestCase
{
    #[DataProvider('dataProvider')]
    public function testAcceptsWithReason(RequirementsConstantArrayType $requirements, Type $context, array $reasons): void
    {
        $result = $requirements->acceptsWithReason($context, true, true, true);

        self::assertSame($reasons, $result->reasons);
    }

    public static function dataProvider(): iterable
    {
        $builder = ConstantArrayTypeBuilder::createEmpty();
        $builder->setOffsetValueType(new ConstantStringType('firstName'), new StringType());
        $builder->setOffsetValueType(new ConstantStringType('lastName'), new UnionType([new StringType(), new IntegerType()]));
        $builder->setOffsetValueType(new ConstantStringType('age'), new StringType());
        $builder->setOffsetValueType(new ConstantStringType('ids'), new ArrayType(new MixedType(), new IntegerType()));
        $requirements = RequirementsConstantArrayType::createFrom($builder->getArray());

        $builder = ConstantArrayTypeBuilder::createEmpty();
        $builder->setOffsetValueType(new ConstantStringType('age'), new IntegerType());
        $builder->setOffsetValueType(new ConstantStringType('email'), new StringType());
        $builder->setOffsetValueType(new ConstantStringType('ids'), new ArrayType(new MixedType(), new StringType()));
        $context = $builder->getArray();

        yield [$requirements, $context, [
            "'firstName' is required but not given.",
            "'lastName' is required but not given.",
            "'age' (string) does not accept given int.",
            "'ids' (array<int>) does not accept given array<string>.",
            "'email' is given but not required.",
        ]];
    }
}

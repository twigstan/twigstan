<?php

declare(strict_types=1);

namespace TwigStan\PHPStan\Type;

use PHPStan\Type\AcceptsResult;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

final class RequirementsConstantArrayType extends ConstantArrayType
{
    public static function createFrom(Type $other): self
    {
        if (!$other->isConstantArray()->yes()) {
            return new self([], []);
        }

        return new self(
            $other->getKeyTypes(),
            array_map(
                fn(Type $type) => $type instanceof self ? self::createFrom($type) : $type,
                $other->getValueTypes(),
            ),
            optionalKeys: $other->getOptionalKeys(),
        );
    }

    public function acceptsWithReason(Type $type, bool $strictTypes, bool $reportMissing = true, bool $reportUnnecessary = false): AcceptsResult
    {
        $result = AcceptsResult::createYes();
        $keyTypes = $this->getKeyTypes();
        $valueTypes = $this->getValueTypes();
        foreach ($keyTypes as $i => $keyType) {
            $valueType = $valueTypes[$i];
            $hasOffsetValueType = $type->hasOffsetValueType($keyType);
            $hasOffset = new AcceptsResult($hasOffsetValueType, $hasOffsetValueType->yes() || !$type->isConstantArray()->yes() ? [] : [sprintf('%s is required but not given.', $keyType->describe(VerbosityLevel::value()))]);
            if ($hasOffset->no()) {
                if ($this->isOptionalKey($i)) {
                    continue;
                }

                if ($reportMissing) {
                    $result = $result->and($hasOffset);
                }

                continue;
            }
            if ($hasOffset->maybe() && $this->isOptionalKey($i)) {
                $hasOffset = AcceptsResult::createYes();
            }
            $result = $result->and($hasOffset);
            $otherValueType = $type->getOffsetValueType($keyType);
            $verbosity = VerbosityLevel::getRecommendedLevelByType($valueType, $otherValueType);
            $acceptsValue = $valueType->acceptsWithReason($otherValueType, $strictTypes)->decorateReasons(static function (string $reason) use ($keyType, $valueType, $verbosity, $otherValueType) {
                return sprintf(
                    '%s (%s) does not accept given %s: %s',
                    $keyType->describe(VerbosityLevel::value()),
                    $valueType->describe($verbosity),
                    $otherValueType->describe($verbosity),
                    $reason,
                );
            });
            if (!$acceptsValue->yes() && count($acceptsValue->reasons) === 0 && $type->isConstantArray()->yes()) {
                $acceptsValue = new AcceptsResult(
                    $acceptsValue->result,
                    [
                        sprintf(
                            '%s (%s) does not accept given %s.',
                            $keyType->describe(VerbosityLevel::value()),
                            $valueType->describe($verbosity),
                            $otherValueType->describe($verbosity),
                        ),
                    ],
                );
            }
            $result = $result->and($acceptsValue);
        }

        if ($reportUnnecessary) {
            $providedButNotRequired = $type->getKeyType()->tryRemove($this->getKeyType())?->getConstantScalarTypes() ?? [];
            foreach ($providedButNotRequired as $keyType) {
                $result = $result->and(
                    AcceptsResult::createNo(
                        [sprintf('%s is given but not required.', $keyType->describe(VerbosityLevel::value()))],
                    ),
                );
            }
        }

        $result = $result->and(new AcceptsResult($type->isArray(), []));
        if ($type->isOversizedArray()->yes()) {
            if (!$result->no()) {
                return AcceptsResult::createYes();
            }
        }
        return $result;
    }
}

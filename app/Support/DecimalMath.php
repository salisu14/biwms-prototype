<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;

final class DecimalMath
{
    public static function of(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }

        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        return BigDecimal::of((string) $value);
    }

    public static function quantity(mixed $value): string
    {
        return self::toScale($value, DecimalPrecision::QUANTITY_SCALE);
    }

    public static function conversion(mixed $value): string
    {
        return self::toScale($value, DecimalPrecision::CONVERSION_SCALE);
    }

    public static function unitCost(mixed $value): string
    {
        return self::toScale($value, DecimalPrecision::UNIT_COST_SCALE);
    }

    public static function amount(mixed $value): string
    {
        return self::toScale($value, DecimalPrecision::AMOUNT_SCALE);
    }

    public static function currency(mixed $value): string
    {
        return self::toScale($value, DecimalPrecision::CURRENCY_SCALE);
    }

    public static function add(mixed $left, mixed $right, int $scale): string
    {
        return (string) self::of($left)->plus(self::of($right))->toScale($scale, DecimalRounding::AMOUNT);
    }

    public static function sub(mixed $left, mixed $right, int $scale): string
    {
        return (string) self::of($left)->minus(self::of($right))->toScale($scale, DecimalRounding::AMOUNT);
    }

    public static function mul(mixed $left, mixed $right, int $scale): string
    {
        return (string) self::of($left)->multipliedBy(self::of($right))->toScale($scale, DecimalRounding::AMOUNT);
    }

    public static function div(mixed $left, mixed $right, int $scale): string
    {
        return (string) self::of($left)->dividedBy(self::of($right), $scale, DecimalRounding::AMOUNT);
    }

    public static function abs(mixed $value, int $scale): string
    {
        return (string) self::of($value)->abs()->toScale($scale, DecimalRounding::AMOUNT);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return self::of($left)->compareTo(self::of($right));
    }

    public static function isPositive(mixed $value): bool
    {
        return self::compare($value, '0') > 0;
    }

    public static function isZero(mixed $value): bool
    {
        return self::compare($value, '0') === 0;
    }

    public static function isLessThanOrEqualToTolerance(mixed $difference, string $tolerance): bool
    {
        return self::of($difference)->abs()->isLessThanOrEqualTo(self::of($tolerance));
    }

    public static function toScale(mixed $value, int $scale): string
    {
        return (string) self::of($value)->toScale($scale, DecimalRounding::AMOUNT);
    }

    public static function trim(mixed $value, int $scale): string
    {
        $scaled = self::toScale($value, $scale);

        return str_contains($scaled, '.')
            ? rtrim(rtrim($scaled, '0'), '.')
            : $scaled;
    }
}

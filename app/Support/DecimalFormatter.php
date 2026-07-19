<?php

declare(strict_types=1);

namespace App\Support;

final class DecimalFormatter
{
    public static function quantity(mixed $value, ?string $unitCode = null): string
    {
        return trim(self::number($value, DecimalPrecision::QUANTITY_SCALE).' '.trim((string) $unitCode));
    }

    public static function quantityForInput(mixed $value): string
    {
        return DecimalMath::trim($value, DecimalPrecision::QUANTITY_SCALE);
    }

    public static function unitCost(mixed $value, ?string $currencyCode = null): string
    {
        return trim(self::number($value, DecimalPrecision::UNIT_COST_SCALE).' '.trim((string) $currencyCode));
    }

    public static function amount(mixed $value, ?string $currencyCode = null): string
    {
        return trim(self::number($value, DecimalPrecision::AMOUNT_SCALE).' '.trim((string) $currencyCode));
    }

    public static function number(mixed $value, int $scale): string
    {
        return DecimalMath::trim($value, $scale);
    }
}

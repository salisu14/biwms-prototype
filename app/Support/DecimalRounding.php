<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\RoundingMode;

final class DecimalRounding
{
    public const QUANTITY = RoundingMode::HALF_UP;

    public const CONVERSION = RoundingMode::HALF_UP;

    public const COST = RoundingMode::HALF_UP;

    public const AMOUNT = RoundingMode::HALF_UP;

    public const CURRENCY = RoundingMode::HALF_UP;
}

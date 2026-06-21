<?php

declare(strict_types=1);

namespace App\Enums;

enum CurrencyExchangeRateType: string
{
    case SPOT = 'spot';
    case M30 = '30_day';
    case M60 = '60_day';
    case M90 = '90_day';
    case HISTORICAL = 'historical';
    case BUDGET = 'budget';

    public function label(): string
    {
        return match ($this) {
            self::SPOT => 'Spot/Current',
            self::M30 => '30 Day',
            self::M60 => '60 Day',
            self::M90 => '90 Day',
            self::HISTORICAL => 'Historical',
            self::BUDGET => 'Budget',
        };
    }
}

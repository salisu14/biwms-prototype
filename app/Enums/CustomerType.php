<?php

namespace App\Enums;

enum CustomerType: string
{
    case RETAIL = 'retail';
    case WHOLESALE = 'wholesale';
    case FOREIGN = 'foreign';

    public function label(): string
    {
        return match ($this) {
            self::RETAIL => 'Retail Customer',
            self::WHOLESALE => 'Wholesale Customer',
            self::FOREIGN => 'Foreign / International Customer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RETAIL => 'info',
            self::WHOLESALE => 'success',
            self::FOREIGN => 'warning',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum ShippingAgentServiceType: string
{
    case GROUND = 'ground';
    case EXPRESS = 'express';
    case OVERNIGHT = 'overnight';
    case INTERNATIONAL = 'international';
    case FREIGHT = 'freight';
    case SAME_DAY = 'same_day';

    public function label(): string
    {
        return match($this) {
            self::GROUND => 'Ground',
            self::EXPRESS => 'Express',
            self::OVERNIGHT => 'Overnight',
            self::INTERNATIONAL => 'International',
            self::FREIGHT => 'Freight',
            self::SAME_DAY => 'Same Day',
        };
    }

    public function defaultTransitDays(): int
    {
        return match($this) {
            self::GROUND => 5,
            self::EXPRESS => 2,
            self::OVERNIGHT => 1,
            self::INTERNATIONAL => 7,
            self::FREIGHT => 10,
            self::SAME_DAY => 0,
        };
    }
}

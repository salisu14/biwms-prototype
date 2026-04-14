<?php

declare(strict_types=1);

namespace App\Enums;

enum MaintenanceContractBillingCycle: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case SEMI_ANNUAL = 'semi_annual';
    case ANNUAL = 'annual';
    case PER_INCIDENT = 'per_incident';
    case PER_HOUR = 'per_hour';
    case FIXED_FEE = 'fixed_fee';

    public function months(): int
    {
        return match($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::SEMI_ANNUAL => 6,
            self::ANNUAL => 12,
            default => 0,
        };
    }
}

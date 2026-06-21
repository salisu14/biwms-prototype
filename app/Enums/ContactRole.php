<?php

namespace App\Enums;

enum ContactRole: string
{
    case CUSTOMER = 'customer';
    case VENDOR = 'vendor';
    case PROSPECT = 'prospect';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Customer',
            self::VENDOR => 'Vendor',
            self::PROSPECT => 'Prospect',
            self::BOTH => 'Customer & Vendor',
        };
    }

    /**
     * Determine if this role is allowed to access the billing portal.
     */
    public function canAccessBilling(): bool
    {
        return match ($this) {
            self::CUSTOMER, self::VENDOR, self::BOTH => true,
            self::PROSPECT => false,
        };
    }

    /**
     * Get a CSS class color for UI badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::CUSTOMER => 'green',
            self::VENDOR => 'blue',
            self::PROSPECT, self::BOTH => 'gray',
        };
    }
}

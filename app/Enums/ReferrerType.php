<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferrerType: string
{
    case INDIVIDUAL = 'INDIVIDUAL';
    case CONTACT = 'CONTACT';
    case EXISTING_CUSTOMER = 'EXISTING_CUSTOMER';
    case EMPLOYEE = 'EMPLOYEE';
    case VENDOR = 'VENDOR';
    case DISTRIBUTOR = 'DISTRIBUTOR';
    case SALES_AGENT = 'SALES_AGENT';
    case ORGANIZATION = 'ORGANIZATION';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::CONTACT => 'Contact',
            self::EXISTING_CUSTOMER => 'Existing Customer',
            self::EMPLOYEE => 'Employee',
            self::VENDOR => 'Vendor',
            self::DISTRIBUTOR => 'Distributor',
            self::SALES_AGENT => 'Sales Agent',
            self::ORGANIZATION => 'Organization',
            self::OTHER => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'info',
            self::CONTACT => 'gray',
            self::EXISTING_CUSTOMER => 'success',
            self::EMPLOYEE => 'warning',
            self::VENDOR => 'purple',
            self::DISTRIBUTOR => 'cyan',
            self::SALES_AGENT => 'orange',
            self::ORGANIZATION => 'primary',
            self::OTHER => 'slate',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'heroicon-o-user',
            self::CONTACT => 'heroicon-o-address-book',
            self::EXISTING_CUSTOMER => 'heroicon-o-users',
            self::EMPLOYEE => 'heroicon-o-identification',
            self::VENDOR => 'heroicon-o-building-storefront',
            self::DISTRIBUTOR => 'heroicon-o-truck',
            self::SALES_AGENT => 'heroicon-o-megaphone',
            self::ORGANIZATION => 'heroicon-o-building-office-2',
            self::OTHER => 'heroicon-o-question-mark-circle',
        };
    }
}

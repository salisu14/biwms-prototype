<?php

namespace App\Enums;

enum SourceType: string
{
    case CUSTOMER = 'CUSTOMER';
    case VENDOR = 'VENDOR';
    case ITEM = 'ITEM';
    case BANK = 'BANK';
    case FIXED_ASSET = 'FIXED_ASSET';
    case EMPLOYEE = 'EMPLOYEE';
    case GENERAL_JOURNAL = 'GENERAL_JOURNAL';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Customer',
            self::VENDOR => 'Vendor',
            self::ITEM => 'Inventory Item',
            self::BANK => 'Bank Account',
            self::FIXED_ASSET => 'Fixed Asset',
            self::EMPLOYEE => 'Employee',
            self::GENERAL_JOURNAL => 'General Journal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CUSTOMER => 'bg-blue-100 text-blue-800',
            self::VENDOR => 'bg-amber-100 text-amber-800',
            self::ITEM => 'bg-emerald-100 text-emerald-800',
            self::BANK => 'bg-indigo-100 text-indigo-800',
            self::FIXED_ASSET => 'bg-purple-100 text-purple-800',
            self::EMPLOYEE => 'bg-teal-100 text-teal-800',
            self::GENERAL_JOURNAL => 'bg-gray-100 text-gray-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CUSTOMER => 'heroicon-o-user-group',
            self::VENDOR => 'heroicon-o-building-storefront',
            self::ITEM => 'heroicon-o-cube',
            self::BANK => 'heroicon-o-building-library',
            self::FIXED_ASSET => 'heroicon-o-wrench-screwdriver',
            self::EMPLOYEE => 'heroicon-o-user',
            self::GENERAL_JOURNAL => 'heroicon-o-book-open',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
            ])
            ->toArray();
    }
}

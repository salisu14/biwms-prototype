<?php

namespace App\Enums;

enum PriceListType: string
{
    case CUSTOMER = 'CUSTOMER';
    case CUSTOMER_GROUP = 'CUSTOMER_GROUP';
    case ALL_CUSTOMERS = 'ALL_CUSTOMERS';
    case CAMPAIGN = 'CAMPAIGN';
    case TRANSFER = 'TRANSFER';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Customer Contract',
            self::CUSTOMER_GROUP => 'Customer Group',
            self::ALL_CUSTOMERS => 'General/All Customers',
            self::CAMPAIGN => 'Marketing Campaign',
            self::TRANSFER => 'Inter-company Transfer',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CUSTOMER => 'bg-indigo-100 text-indigo-800',
            self::CUSTOMER_GROUP => 'bg-blue-100 text-blue-800',
            self::ALL_CUSTOMERS => 'bg-slate-100 text-slate-800',
            self::CAMPAIGN => 'bg-rose-100 text-rose-800',
            self::TRANSFER => 'bg-teal-100 text-teal-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CUSTOMER => 'heroicon-o-user',
            self::CUSTOMER_GROUP => 'heroicon-o-user-group',
            self::ALL_CUSTOMERS => 'heroicon-o-globe-alt',
            self::CAMPAIGN => 'heroicon-o-megaphone',
            self::TRANSFER => 'heroicon-o-arrows-right-left',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Specific pricing negotiated for an individual customer.',
            self::CUSTOMER_GROUP => 'Pricing applicable to a specific segment or group of customers.',
            self::ALL_CUSTOMERS => 'Standard base pricing available to all customers.',
            self::CAMPAIGN => 'Promotional pricing valid for a specific time period.',
            self::TRANSFER => 'Internal pricing used for transfers between locations or entities.',
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
                'description' => $case->description(),
            ])
            ->toArray();
    }
}

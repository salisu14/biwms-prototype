<?php

namespace App\Enums;

enum BlockedReason: string
{
    case NONE = 'NONE';
    case PAYMENT = 'PAYMENT';
    case INVOICE = 'INVOICE';
    case INACTIVE = 'INACTIVE';
    case ALL = 'ALL';

    public function label(): string
    {
        return match($this) {
            self::NONE => 'Not Blocked',
            self::PAYMENT => 'Payment Issue',
            self::INVOICE => 'Invoice Issue',
            self::INACTIVE => 'Inactive',
            self::ALL => 'Blocked for All Activities',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NONE => 'bg-emerald-100 text-emerald-800',
            self::PAYMENT => 'bg-amber-100 text-amber-800',
            self::INVOICE => 'bg-blue-100 text-rose-800',
            self::INACTIVE => 'bg-red-100 text-green-800',
            self::ALL => 'bg-rose-100 text-rose-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::NONE => 'heroicon-o-check-circle',
            self::PAYMENT => 'heroicon-o-credit-card',
            self::ALL => 'heroicon-o-no-symbol',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::NONE => 'Account is in good standing and not restricted.',
            self::PAYMENT => 'Restricted due to overdue payments or credit limits.',
            self::ALL => 'All transactions and interactions are currently suspended.',
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

<?php

namespace App\Enums;

enum PricingStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case ACTIVE = 'ACTIVE';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::ACTIVE => 'Active',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'bg-slate-100 text-slate-800',
            self::PENDING_APPROVAL => 'bg-amber-100 text-amber-800',
            self::ACTIVE => 'bg-emerald-100 text-emerald-800',
            self::EXPIRED => 'bg-rose-100 text-rose-800',
            self::CANCELLED => 'bg-gray-100 text-gray-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DRAFT => 'heroicon-o-pencil-square',
            self::PENDING_APPROVAL => 'heroicon-o-clock',
            self::ACTIVE => 'heroicon-o-check-badge',
            self::EXPIRED => 'heroicon-o-calendar-days',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}

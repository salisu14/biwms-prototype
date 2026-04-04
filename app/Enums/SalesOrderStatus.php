<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Enum for Sales Order Status with capitalized labels
 */
enum SalesOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case RELEASED = 'RELEASED';
    case PICKING = 'PICKING';
    case PACKED = 'PACKED';
    case SHIPPED = 'SHIPPED';
    case INVOICED = 'INVOICED';
    case PARTIALLY_INVOICED = 'PARTIALLY_INVOICED';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'DRAFT',
            self::PENDING_APPROVAL => 'PENDING APPROVAL',
            self::APPROVED => 'APPROVED',
            self::RELEASED => 'RELEASED',
            self::PICKING => 'PICKING',
            self::PACKED => 'PACKED',
            self::SHIPPED => 'SHIPPED',
            self::INVOICED => 'INVOICED',
            self::PARTIALLY_INVOICED => 'PARTIALLY INVOICED',
            self::CLOSED => 'CLOSED',
            self::CANCELLED => 'CANCELLED',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_APPROVAL => 'warning',
            self::APPROVED, self::RELEASED => 'info',
            self::PICKING, self::PACKED => 'primary',
            self::SHIPPED, self::INVOICED, self::CLOSED => 'success',
            self::PARTIALLY_INVOICED => 'info',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-pencil-square',
            self::PENDING_APPROVAL => 'heroicon-m-clock',
            self::APPROVED => 'heroicon-m-check-circle',
            self::RELEASED => 'heroicon-m-rocket-launch',
            self::PICKING => 'heroicon-m-archive-box',
            self::PACKED => 'heroicon-m-gift',
            self::SHIPPED => 'heroicon-m-truck',
            self::INVOICED, self::CLOSED => 'heroicon-m-check-badge',
            self::CANCELLED => 'heroicon-m-x-circle',
            default => null,
        };
    }
}

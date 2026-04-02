<?php
// app/Enums/PurchaseOrderStatus.php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case RECEIVED = 'RECEIVED';
    case INVOICED = 'INVOICED';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';
    case PARTIALLY_RECEIVED = 'PARTIALLY_RECEIVED';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::RECEIVED => 'Received',
            self::INVOICED => 'Invoiced',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
            self::PARTIALLY_RECEIVED => 'Partially Received',
        };
    }

    /**
     * Get color for Filament/UI
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::RECEIVED => 'info',
            self::INVOICED => 'primary',
            self::CLOSED => 'secondary',
            self::CANCELLED => 'danger',
            self::PARTIALLY_RECEIVED => 'warning',
        };
    }

    /**
     * Get icon for Filament/UI
     */
    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'heroicon-m-clock',
            self::APPROVED => 'heroicon-m-check-badge',
            self::RECEIVED => 'heroicon-m-truck',
            self::INVOICED => 'heroicon-m-document-text',
            self::CLOSED => 'heroicon-m-lock-closed',
            self::CANCELLED => 'heroicon-m-x-circle',
        };
    }

    /**
     * Check if status allows editing
     */
    public function canEdit(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED]);
    }

    /**
     * Check if status allows receiving
     */
    public function canReceive(): bool
    {
        return in_array($this, [self::APPROVED, self::PARTIALLY_RECEIVED]);
    }

    /**
     * Get all values as array for validation/selection
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get options for Filament select
     */
    public static function options(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->value] = $case->label();
            return $carry;
        }, []);
    }
}

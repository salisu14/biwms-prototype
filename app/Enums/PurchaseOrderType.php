<?php
// app/Enums/PurchaseOrderType.php

namespace App\Enums;

enum PurchaseOrderType: string
{
    case PURCHASE_ORDER = 'purchase_order';
    case PURCHASE_RETURN = 'purchase_return';
    case PURCHASE_INVOICE = 'purchase_invoice';

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'Purchase Order',
            self::PURCHASE_RETURN => 'Purchase Return',
            self::PURCHASE_INVOICE => 'Purchase Invoice',
        };
    }

    /**
     * Get short code for document numbering
     */
    public function code(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'P',
            self::PURCHASE_RETURN => 'PR',
            self::PURCHASE_INVOICE => 'PI',
        };
    }

    /**
     * Get number series code
     */
    public function seriesCode(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'PURCHASE',
            self::PURCHASE_RETURN => 'PURCHASE_RETURN',
            self::PURCHASE_INVOICE => 'PURCHASE_INVOICE',
        };
    }

    /**
     * Get color for Filament/UI
     */
    public function color(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'primary',
            self::PURCHASE_RETURN => 'warning',
            self::PURCHASE_INVOICE => 'success',
        };
    }

    /**
     * Get icon for Filament/UI
     */
    public function icon(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'heroicon-m-shopping-cart',
            self::PURCHASE_RETURN => 'heroicon-m-arrow-uturn-left',
            self::PURCHASE_INVOICE => 'heroicon-m-document-currency-dollar',
        };
    }

    /**
     * Check if this is a return type
     */
    public function isReturn(): bool
    {
        return $this === self::PURCHASE_RETURN;
    }

    /**
     * Check if this is an invoice type
     */
    public function isInvoice(): bool
    {
        return $this === self::PURCHASE_INVOICE;
    }

    /**
     * Check if quantities should be negative (returns)
     */
    public function isNegativeQuantity(): bool
    {
        return $this === self::PURCHASE_RETURN;
    }

    /**
     * Get all values as array
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

    /**
     * Get options with icons for Filament
     */
    public static function optionsWithIcons(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->value] = [
                'label' => $case->label(),
                'icon' => $case->icon(),
                'color' => $case->color(),
            ];
            return $carry;
        }, []);
    }
}

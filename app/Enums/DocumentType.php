<?php
// app/Enums/DocumentType.php

namespace App\Enums;

enum DocumentType: string
{
    case PURCHASE_ORDER = 'PURCHASE_ORDER';
    case PRODUCTION_ORDER = 'PRODUCTION_ORDER';
    case SALES_ORDER = 'SALES_ORDER';
    case TRANSFER_ORDER = 'TRANSFER_ORDER';
    case ADJUSTMENT = 'ADJUSTMENT';
    case RETURN = 'RETURN';
    case SCRAP = 'SCRAP';

    /**
     * Human-readable label
     */
    public function label(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'Purchase Order',
            self::PRODUCTION_ORDER => 'Production Order',
            self::SALES_ORDER => 'Sales Order',
            self::TRANSFER_ORDER => 'Transfer Order',
            self::ADJUSTMENT => 'Inventory Adjustment',
            self::RETURN => 'Customer Return',
            self::SCRAP => 'Scrap/Waste',
        };
    }

    /**
     * Color for UI badges (Tailwind classes)
     */
    public function color(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'bg-blue-100 text-blue-800 border-blue-200',
            self::PRODUCTION_ORDER => 'bg-purple-100 text-purple-800 border-purple-200',
            self::SALES_ORDER => 'bg-green-100 text-green-800 border-green-200',
            self::TRANSFER_ORDER => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            self::ADJUSTMENT => 'bg-orange-100 text-orange-800 border-orange-200',
            self::RETURN => 'bg-red-100 text-red-800 border-red-200',
            self::SCRAP => 'bg-gray-100 text-gray-800 border-gray-200',
        };
    }

    /**
     * Icon for UI
     */
    public function icon(): string
    {
        return match($this) {
            self::PURCHASE_ORDER => 'shopping-cart',
            self::PRODUCTION_ORDER => 'factory',
            self::SALES_ORDER => 'truck',
            self::TRANSFER_ORDER => 'exchange-alt',
            self::ADJUSTMENT => 'balance-scale',
            self::RETURN => 'undo',
            self::SCRAP => 'trash',
        };
    }

    /**
     * Whether this document type affects financials
     */
    public function isFinancial(): bool
    {
        return in_array($this, [
            self::PURCHASE_ORDER,
            self::SALES_ORDER,
            self::ADJUSTMENT,
        ]);
    }

    /**
     * Get all values for select dropdowns
     */
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

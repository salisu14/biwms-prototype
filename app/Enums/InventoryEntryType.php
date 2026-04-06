<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InventoryEntryType: string implements HasLabel, HasColor
{
    case PURCHASE = 'purchase';
    case PURCHASE_RETURN = 'purchase_return';
    case SALE = 'sale';
    case SALES_RETURN = 'sales_return';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case POSITIVE_ADJUSTMENT = 'positive_adjustment';
    case NEGATIVE_ADJUSTMENT = 'negative_adjustment';

    /**
     * Human-readable labels for UI components (Filament Tables/Forms).
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::PURCHASE_RETURN => 'Purchase Return',
            self::SALE => 'Sale',
            self::SALES_RETURN => 'Sales Return',
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::POSITIVE_ADJUSTMENT => 'Adjustment (+)',
            self::NEGATIVE_ADJUSTMENT => 'Adjustment (-)',
        };
    }

    /**
     * Semantic colors for badges or status indicators.
     */
    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PURCHASE, self::SALES_RETURN, self::TRANSFER_IN, self::POSITIVE_ADJUSTMENT => 'success',
            self::SALE, self::PURCHASE_RETURN, self::TRANSFER_OUT, self::NEGATIVE_ADJUSTMENT => 'danger',
        };
    }

    /**
     * Returns 1 for stock addition and -1 for stock reduction.
     * Useful for: $ledger->quantity * $ledger->entry_type->direction()
     */
    public function direction(): int
    {
        return match ($this) {
            self::PURCHASE,
            self::SALES_RETURN,
            self::TRANSFER_IN,
            self::POSITIVE_ADJUSTMENT => 1,

            self::SALE,
            self::PURCHASE_RETURN,
            self::TRANSFER_OUT,
            self::NEGATIVE_ADJUSTMENT => -1,
        };
    }

    /**
     * Categorize entries for reporting.
     */
    public function isAdjustment(): bool
    {
        return in_array($this, [self::POSITIVE_ADJUSTMENT, self::NEGATIVE_ADJUSTMENT]);
    }

    public function isTransfer(): bool
    {
        return in_array($this, [self::TRANSFER_IN, self::TRANSFER_OUT]);
    }
}

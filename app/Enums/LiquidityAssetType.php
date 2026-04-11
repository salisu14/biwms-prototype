<?php

declare(strict_types=1);

namespace App\Enums;

enum LiquidityAssetType: string
{
    case CASH_HAND = 'cash_hand';
    case CASH_BANK = 'cash_bank';
    case ACCOUNTS_RECEIVABLE = 'accounts_receivable';
    case ADVANCE_VENDOR = 'advance_vendor';
    case ADVANCE_STAFF = 'advance_staff';
    case INVENTORY = 'inventory';
    case SHORT_TERM_INVESTMENT = 'short_term_investment';
    case PREPAID_EXPENSES = 'prepaid_expenses';

    public function label(): string
    {
        return match ($this) {
            self::CASH_HAND => 'Cash in Hand',
            self::CASH_BANK => 'Cash in Bank',
            self::ACCOUNTS_RECEIVABLE => 'Accounts Receivable',
            self::ADVANCE_VENDOR => 'Advance to Vendor',
            self::ADVANCE_STAFF => 'Advance to Staff',
            self::INVENTORY => 'Inventory',
            self::SHORT_TERM_INVESTMENT => 'Short-term Investment',
            self::PREPAID_EXPENSES => 'Prepaid Expenses',
        };
    }

    public function isCashEquivalent(): bool
    {
        return in_array($this, [self::CASH_HAND, self::CASH_BANK, self::SHORT_TERM_INVESTMENT], true);
    }

    public function requiresSubLedger(): bool
    {
        return in_array($this, [
            self::ACCOUNTS_RECEIVABLE,
            self::ADVANCE_VENDOR,
            self::ADVANCE_STAFF,
            self::INVENTORY,
        ], true);
    }

    public function isBankRelated(): bool
    {
        return $this === self::CASH_BANK;
    }
}

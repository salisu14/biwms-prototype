<?php

declare(strict_types=1);

namespace App\Enums;

enum BankAccountLedgerEntryStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case RECONCILED = 'reconciled';
    case PENDING = 'pending';
    case VOID = 'void';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
            self::RECONCILED => 'Reconciled',
            self::PENDING => 'Pending',
            self::VOID => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPEN => 'warning',      // Yellow/Amber
            self::CLOSED => 'gray',       // Gray
            self::RECONCILED => 'success', // Green
            self::PENDING => 'info',      // Blue
            self::VOID => 'danger',       // Red
        };
    }

    public function canReconcile(): bool
    {
        return $this === self::OPEN || $this === self::PENDING;
    }

    public function canVoid(): bool
    {
        return in_array($this, [self::OPEN, self::PENDING], true);
    }
}

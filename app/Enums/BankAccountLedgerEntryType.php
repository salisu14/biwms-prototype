<?php

declare(strict_types=1);

namespace App\Enums;

enum BankAccountLedgerEntryType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAWAL = 'withdrawal';
    case TRANSFER = 'transfer';
    case CHECK = 'check';
    case BANK_CHARGE = 'bank_charge';
    case INTEREST = 'interest';
    case REVERSAL = 'reversal';
    case RECONCILIATION = 'reconciliation';

    public function label(): string
    {
        return match ($this) {
            self::DEPOSIT => 'Deposit',
            self::WITHDRAWAL => 'Withdrawal',
            self::TRANSFER => 'Transfer',
            self::CHECK => 'Check',
            self::BANK_CHARGE => 'Bank Charge',
            self::INTEREST => 'Interest',
            self::REVERSAL => 'Reversal',
            self::RECONCILIATION => 'Reconciliation',
        };
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::WITHDRAWAL, self::CHECK, self::BANK_CHARGE], true);
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::DEPOSIT, self::INTEREST], true);
    }

    public function affectsBalance(): int
    {
        return match ($this) {
            self::DEPOSIT, self::INTEREST => 1,
            self::WITHDRAWAL, self::CHECK, self::BANK_CHARGE => -1,
            self::TRANSFER, self::REVERSAL, self::RECONCILIATION => 0,
        };
    }
}

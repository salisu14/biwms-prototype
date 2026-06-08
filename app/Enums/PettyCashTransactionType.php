<?php

namespace App\Enums;

enum PettyCashTransactionType: string
{
    case PAYMENT = 'payment';
    case REPLENISHMENT = 'replenishment';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match($this) {
            self::PAYMENT => 'Payment',
            self::REPLENISHMENT => 'Replenishment',
            self::ADJUSTMENT => 'Adjustment',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PAYMENT => 'danger',
            self::REPLENISHMENT => 'success',
            self::ADJUSTMENT => 'warning',
        };
    }
}

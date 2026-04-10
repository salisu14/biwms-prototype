<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayCodeType: string implements HasLabel
{
    case EARNING = 'EARNING';
    case DEDUCTION = 'DEDUCTION';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EARNING => 'Earning (Income)',
            self::DEDUCTION => 'Deduction (Expense)',
        };
    }
}

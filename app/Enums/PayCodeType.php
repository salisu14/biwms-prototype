<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayCodeType: string implements HasLabel
{
    case EARNING = 'EARNING';
    case DEDUCTION = 'DEDUCTION';
    case BENEFIT = 'BENEFIT';
    case INFORMATIONAL = 'INFORMATIONAL';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EARNING => 'Earning',
            self::DEDUCTION => 'Deduction',
            self::BENEFIT => 'Benefit',
            self::INFORMATIONAL => 'Informational',
        };
    }
}

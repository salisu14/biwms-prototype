<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum VatCalculationType: string implements HasDescription, HasLabel
{
    case Normal = 'normal';
    case ReverseCharge = 'reverse_charge';
    case Full = 'full';
    case SalesTax = 'sales_tax';

    public function getLabel(): string
    {
        return match ($this) {
            self::Normal => 'Normal VAT',
            self::ReverseCharge => 'Reverse Charge VAT',
            self::Full => 'Full VAT',
            self::SalesTax => 'Sales Tax',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Normal => 'Standard VAT calculation (Qty * Price * %).',
            self::ReverseCharge => 'Customer is responsible for paying the VAT.',
            self::Full => 'Full amount is treated as VAT.',
            self::SalesTax => 'US-style Sales Tax calculation.',
        };
    }
}

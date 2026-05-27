<?php

namespace App\Enums;

// app/Enums/Company/CurrencyCode.php
enum CurrencyCode: string
{
    case NGN = 'NGN';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case GHS = 'GHS';
    case KES = 'KES';
    case ZAR = 'ZAR';

    public function label(): string
    {
        return match ($this) {
            self::NGN => 'Nigerian Naira',
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
            self::GBP => 'British Pound',
            self::GHS => 'Ghanaian Cedi',
            self::KES => 'Kenyan Shilling',
            self::ZAR => 'South African Rand',
        };
    }

    public static function toArray(): array
    {
        return array_reduce(self::cases(), fn ($c, $case) => $c + [$case->value => $case->label()], []);
    }
}

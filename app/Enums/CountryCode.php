<?php

namespace App\Enums;

// app/Enums/Company/CountryCode.php
enum CountryCode: string
{
    case NGA = 'NGA';
    case GHA = 'GHA';
    case KEN = 'KEN';
    case ZAF = 'ZAF';
    case USA = 'USA';
    case GBR = 'GBR';

    public function label(): string
    {
        return match ($this) {
            self::NGA => 'Nigeria',
            self::GHA => 'Ghana',
            self::KEN => 'Kenya',
            self::ZAF => 'South Africa',
            self::USA => 'United States',
            self::GBR => 'United Kingdom',
        };
    }

    public static function toArray(): array
    {
        return array_reduce(self::cases(), fn ($c, $case) => $c + [$case->value => $case->label()], []);
    }
}

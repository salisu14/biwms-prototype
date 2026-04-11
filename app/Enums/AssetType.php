<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetType: string
{
    case FIXED = 'fixed';
    case LIQUIDITY = 'liquidity';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixed Asset',
            self::LIQUIDITY => 'Liquidity Asset (Current)',
        };
    }
}

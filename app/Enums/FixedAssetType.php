<?php

declare(strict_types=1);

namespace App\Enums;

enum FixedAssetType: string
{
    case TANGIBLE = 'tangible';           // Physical assets: buildings, machinery, vehicles
    case INTANGIBLE = 'intangible';       // Patents, copyrights, software, goodwill
    case FINANCIAL = 'financial';         // Long-term investments, loans to subsidiaries
    case OPERATING = 'operating';         // Leased assets (IFRS 16)
    case RIGHT_OF_USE = 'right_of_use';   // ROU assets under lease accounting

    public function isDepreciable(): bool
    {
        return in_array($this, [self::TANGIBLE, self::OPERATING, self::RIGHT_OF_USE]);
    }

    public function isAmortizable(): bool
    {
        return $this === self::INTANGIBLE;
    }
}

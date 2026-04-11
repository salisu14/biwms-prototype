<?php

declare(strict_types=1);

namespace App\Enums;

enum FixedAssetCategory: string
{
    case TANGIBLE = 'tangible';
    case INTANGIBLE = 'intangible';

    public function label(): string
    {
        return match ($this) {
            self::TANGIBLE => 'Tangible Asset',
            self::INTANGIBLE => 'Intangible Asset',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayrollStatus: string implements HasColor, HasLabel
{
    case OPEN = 'OPEN';
    case CALCULATED = 'CALCULATED';
    case APPROVED = 'APPROVED';
    case POSTED = 'POSTED';
    case VOIDED = 'VOIDED';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CALCULATED => 'Calculated',
            self::APPROVED => 'Approved',
            self::POSTED => 'Posted',
            self::VOIDED => 'Voided',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'gray',
            self::CALCULATED => 'info',
            self::APPROVED => 'warning',
            self::POSTED => 'success',
            self::VOIDED => 'danger',
        };
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayrollPeriodStatus: string implements HasColor, HasLabel
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
    case POSTED = 'POSTED';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
            self::POSTED => 'Posted',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'info',
            self::CLOSED => 'warning',
            self::POSTED => 'success',
        };
    }
}

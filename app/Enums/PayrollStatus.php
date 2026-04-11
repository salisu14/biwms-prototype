<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayrollStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'DRAFT';
    case APPROVED = 'APPROVED';
    case POSTED = 'POSTED';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
            self::POSTED => 'Posted',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::APPROVED => 'warning',
            self::POSTED => 'success',
        };
    }
}

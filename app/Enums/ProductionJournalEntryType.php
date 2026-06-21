<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductionJournalEntryType: string implements HasColor, HasLabel
{
    case Consumption = 'consumption';
    case Output = 'output';
    case Capacity = 'capacity';
    case Scrap = 'scrap';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Consumption => 'Consumption (Material)',
            self::Output => 'Output (Finished Good)',
            self::Capacity => 'Capacity (Labor/Machine)',
            self::Scrap => 'Scrap',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Consumption => 'warning',
            self::Output => 'success',
            self::Capacity => 'info',
            self::Scrap => 'danger',
        };
    }
}

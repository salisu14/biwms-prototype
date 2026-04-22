<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;

enum ProductionJournalEntryType: string implements HasLabel, HasColor
{
    case Consumption = 'consumption';
    case Output = 'output';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Consumption => 'Consumption (Material)',
            self::Output => 'Output (Finished Good/Capacity)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Consumption => 'warning',
            self::Output => 'success',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductionDowntimeCategory: string implements HasLabel
{
    case Planned = 'planned';
    case Unplanned = 'unplanned';
    case Quality = 'quality';
    case Maintenance = 'maintenance';
    case MaterialShortage = 'material_shortage';
    case Changeover = 'changeover';

    public function getLabel(): ?string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductionScrapStage: string implements HasLabel
{
    case Material = 'material';
    case Setup = 'setup';
    case Process = 'process';
    case Quality = 'quality';
    case Packaging = 'packaging';
    case FinishedOutput = 'finished_output';

    public function getLabel(): ?string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}

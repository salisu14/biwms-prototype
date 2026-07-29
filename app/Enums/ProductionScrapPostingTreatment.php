<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductionScrapPostingTreatment: string implements HasLabel
{
    case OperationalOnly = 'operational_only';
    case AdditionalConsumption = 'additional_consumption';
    case ReducedOutput = 'reduced_output';
    case ProductionVariance = 'production_variance';
    case RecoverableMaterial = 'recoverable_material';
    case ReworkRequired = 'rework_required';

    public function getLabel(): ?string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}

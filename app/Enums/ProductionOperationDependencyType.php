<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionOperationDependencyType: string
{
    case FinishToStart = 'finish_to_start';
    case OutputAvailableToStart = 'output_available_to_start';
    case QualityReleasedToStart = 'quality_released_to_start';
    case SupplyAvailableToStart = 'supply_available_to_start';
}

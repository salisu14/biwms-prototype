<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionCalculations\Pages;

use App\Filament\Resources\CommissionCalculations\CommissionCalculationResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionCalculations extends ListRecords
{
    protected static string $resource = CommissionCalculationResource::class;
}

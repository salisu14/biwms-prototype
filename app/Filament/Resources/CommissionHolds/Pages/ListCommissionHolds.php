<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionHolds\Pages;

use App\Filament\Resources\CommissionHolds\CommissionHoldResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionHolds extends ListRecords
{
    protected static string $resource = CommissionHoldResource::class;
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionHolds\Pages;

use App\Filament\Resources\CommissionHolds\CommissionHoldResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCommissionHold extends ViewRecord
{
    protected static string $resource = CommissionHoldResource::class;
}

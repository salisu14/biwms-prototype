<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionSchedules\Pages;

use App\Filament\Resources\Manufacturing\ProductionSchedules\ProductionScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionSchedule extends CreateRecord
{
    protected static string $resource = ProductionScheduleResource::class;
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDowntimeReasons\Pages;

use App\Filament\Resources\ProductionDowntimeReasons\ProductionDowntimeReasonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionDowntimeReason extends CreateRecord
{
    protected static string $resource = ProductionDowntimeReasonResource::class;
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDowntimeReasons\Pages;

use App\Filament\Resources\ProductionDowntimeReasons\ProductionDowntimeReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionDowntimeReasons extends ListRecords
{
    protected static string $resource = ProductionDowntimeReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

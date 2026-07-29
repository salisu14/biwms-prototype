<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDowntimeReasons\Pages;

use App\Filament\Resources\ProductionDowntimeReasons\ProductionDowntimeReasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionDowntimeReason extends EditRecord
{
    protected static string $resource = ProductionDowntimeReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

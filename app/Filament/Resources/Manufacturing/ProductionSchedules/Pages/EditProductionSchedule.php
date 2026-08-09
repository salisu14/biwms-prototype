<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionSchedules\Pages;

use App\Filament\Resources\Manufacturing\ProductionSchedules\ProductionScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionSchedule extends EditRecord
{
    protected static string $resource = ProductionScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\EmployeePromotionHistories\Pages;

use App\Filament\Resources\EmployeePromotionHistories\EmployeePromotionHistoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePromotionHistory extends EditRecord
{
    protected static string $resource = EmployeePromotionHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

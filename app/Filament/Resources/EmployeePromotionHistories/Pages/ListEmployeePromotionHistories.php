<?php

namespace App\Filament\Resources\EmployeePromotionHistories\Pages;

use App\Filament\Resources\EmployeePromotionHistories\EmployeePromotionHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePromotionHistories extends ListRecords
{
    protected static string $resource = EmployeePromotionHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

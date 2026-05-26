<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSalesOrders extends ListRecords
{
    protected static string $resource = SalesOrderResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->whereNotIn('status', [
                SalesOrderStatus::CLOSED->value,
                SalesOrderStatus::CANCELLED->value,
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

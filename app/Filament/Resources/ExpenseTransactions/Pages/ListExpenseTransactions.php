<?php

namespace App\Filament\Resources\ExpenseTransactions\Pages;

use App\Filament\Resources\ExpenseTransactions\ExpenseTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenseTransactions extends ListRecords
{
    protected static string $resource = ExpenseTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

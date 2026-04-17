<?php

namespace App\Filament\Resources\ExpenseTransactions\Pages;

use App\Filament\Resources\ExpenseTransactions\ExpenseTransactionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExpenseTransaction extends ViewRecord
{
    protected static string $resource = ExpenseTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

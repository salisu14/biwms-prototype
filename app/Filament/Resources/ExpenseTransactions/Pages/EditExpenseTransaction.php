<?php

namespace App\Filament\Resources\ExpenseTransactions\Pages;

use App\Filament\Resources\ExpenseTransactions\ExpenseTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditExpenseTransaction extends EditRecord
{
    protected static string $resource = ExpenseTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\ExpenseTransactions\Pages;

use App\Filament\Resources\ExpenseTransactions\ExpenseTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseTransaction extends CreateRecord
{
    protected static string $resource = ExpenseTransactionResource::class;
}

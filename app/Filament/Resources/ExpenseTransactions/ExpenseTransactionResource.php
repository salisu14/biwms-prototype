<?php

namespace App\Filament\Resources\ExpenseTransactions;

use App\Filament\Resources\ExpenseTransactions\Pages\CreateExpenseTransaction;
use App\Filament\Resources\ExpenseTransactions\Pages\EditExpenseTransaction;
use App\Filament\Resources\ExpenseTransactions\Pages\ListExpenseTransactions;
use App\Filament\Resources\ExpenseTransactions\Pages\ViewExpenseTransaction;
use App\Filament\Resources\ExpenseTransactions\Schemas\ExpenseTransactionForm;
use App\Filament\Resources\ExpenseTransactions\Schemas\ExpenseTransactionInfolist;
use App\Filament\Resources\ExpenseTransactions\Tables\ExpenseTransactionsTable;
use App\Models\ExpenseTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseTransactionResource extends Resource
{
    protected static ?string $model = ExpenseTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ExpenseTransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExpenseTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AllocationsRelationManager::class,
            RelationManagers\GlEntryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseTransactions::route('/'),
            'create' => CreateExpenseTransaction::route('/create'),
            'view' => ViewExpenseTransaction::route('/{record}'),
            'edit' => EditExpenseTransaction::route('/{record}/edit'),
        ];
    }
}

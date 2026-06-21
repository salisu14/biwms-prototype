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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseTransactionResource extends Resource
{
    protected static ?string $model = ExpenseTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'document_no';

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

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_no',
            'description',
            'invoice_no',
            'purchase_order_no',
            'sales_order_no',
            'vendor.vendor_name',
            'customer.name',
            'employee.full_name',
            'expenseCategory.description',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ExpenseTransaction $record */
        return [
            'Description' => $record->description ?: '—',
            'Vendor' => $record->vendor?->vendor_name ?: '—',
            'Status' => $record->status ?: '—',
            'Amount' => number_format((float) $record->amount, 2).' '.($record->currency_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'vendor',
            'customer',
            'employee',
            'expenseCategory',
        ]);
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

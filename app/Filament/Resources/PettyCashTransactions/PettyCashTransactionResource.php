<?php

namespace App\Filament\Resources\PettyCashTransactions;

use App\Filament\Resources\PettyCashTransactions\Pages\CreatePettyCashTransaction;
use App\Filament\Resources\PettyCashTransactions\Pages\EditPettyCashTransaction;
use App\Filament\Resources\PettyCashTransactions\Pages\ListPettyCashTransactions;
use App\Filament\Resources\PettyCashTransactions\Pages\ViewPettyCashTransaction;
use App\Filament\Resources\PettyCashTransactions\Schemas\PettyCashTransactionForm;
use App\Filament\Resources\PettyCashTransactions\Schemas\PettyCashTransactionInfolist;
use App\Filament\Resources\PettyCashTransactions\Tables\PettyCashTransactionsTable;
use App\Models\PettyCashTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PettyCashTransactionResource extends Resource
{
    protected static ?string $model = PettyCashTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowsRightLeft;

    protected static string|null|\UnitEnum $navigationGroup = 'Cash Management';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole([
            'finance-manager',
            'finance-accountant',
            'super_admin',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PettyCashTransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PettyCashTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PettyCashTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPettyCashTransactions::route('/'),
//            'create' => CreatePettyCashTransaction::route('/create'),
            'view' => ViewPettyCashTransaction::route('/{record}'),
//            'edit' => EditPettyCashTransaction::route('/{record}/edit'),
        ];
    }
}

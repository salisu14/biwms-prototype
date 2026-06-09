<?php

namespace App\Filament\Resources\BankAccountLedgerEntries;

use App\Filament\Resources\BankAccountLedgerEntries\Pages\ListBankAccountLedgerEntries;
use App\Filament\Resources\BankAccountLedgerEntries\Pages\ViewBankAccountLedgerEntry;
use App\Filament\Resources\BankAccountLedgerEntries\Schemas\BankAccountLedgerEntryForm;
use App\Filament\Resources\BankAccountLedgerEntries\Schemas\BankAccountLedgerEntryInfolist;
use App\Filament\Resources\BankAccountLedgerEntries\Tables\BankAccountLedgerEntriesTable;
use App\Models\BankAccountLedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BankAccountLedgerEntryResource extends Resource
{
    protected static ?string $model = BankAccountLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BankAccountLedgerEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BankAccountLedgerEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountLedgerEntriesTable::configure($table);
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
            'index' => ListBankAccountLedgerEntries::route('/'),
            'view' => ViewBankAccountLedgerEntry::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

<?php

namespace App\Filament\Resources\CustomerLedgerEntries;

use App\Filament\Resources\CustomerLedgerEntries\Pages\CreateCustomerLedgerEntry;
use App\Filament\Resources\CustomerLedgerEntries\Pages\EditCustomerLedgerEntry;
use App\Filament\Resources\CustomerLedgerEntries\Pages\ListCustomerLedgerEntries;
use App\Filament\Resources\CustomerLedgerEntries\Schemas\CustomerLedgerEntryForm;
use App\Filament\Resources\CustomerLedgerEntries\Tables\CustomerLedgerEntriesTable;
use App\Models\CustomerLedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerLedgerEntryResource extends Resource
{
    protected static ?string $model = CustomerLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CustomerLedgerEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerLedgerEntriesTable::configure($table);
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
            'index' => ListCustomerLedgerEntries::route('/'),
            'create' => CreateCustomerLedgerEntry::route('/create'),
            'edit' => EditCustomerLedgerEntry::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\VendorLedgerEntries;

use App\Filament\Resources\VendorLedgerEntries\Pages\CreateVendorLedgerEntry;
use App\Filament\Resources\VendorLedgerEntries\Pages\EditVendorLedgerEntry;
use App\Filament\Resources\VendorLedgerEntries\Pages\ListVendorLedgerEntries;
use App\Filament\Resources\VendorLedgerEntries\Pages\ViewVendorLedgerEntry;
use App\Filament\Resources\VendorLedgerEntries\Schemas\VendorLedgerEntryForm;
use App\Filament\Resources\VendorLedgerEntries\Schemas\VendorLedgerEntryInfolist;
use App\Filament\Resources\VendorLedgerEntries\Tables\VendorLedgerEntriesTable;
use App\Models\VendorLedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VendorLedgerEntryResource extends Resource
{
    protected static ?string $model = VendorLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return VendorLedgerEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorLedgerEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorLedgerEntriesTable::configure($table);
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
            'index' => ListVendorLedgerEntries::route('/'),
            'create' => CreateVendorLedgerEntry::route('/create'),
            'view' => ViewVendorLedgerEntry::route('/{record}'),
            'edit' => EditVendorLedgerEntry::route('/{record}/edit'),
        ];
    }
}

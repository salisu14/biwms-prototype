<?php

namespace App\Filament\Resources\VendorLedgerEntries;

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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class VendorLedgerEntryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'procurement';
    }

    public static function permissionResource(): string
    {
        return 'vendor_ledger_entry';
    }

    protected static ?string $model = VendorLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?int $globalSearchSort = 180;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['vendor', 'currency']);
    }

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

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof VendorLedgerEntry) {
            return static::getModelLabel();
        }

        $vendor = $record->vendor
            ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
            : 'Unknown Vendor';

        return "{$record->entry_number} - {$vendor}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'entry_number',
            'vendor.vendor_code',
            'vendor.vendor_name',
            'document_type',
            'document_number',
            'external_document_number',
            'description',
            'remaining_amount',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var VendorLedgerEntry $record */
        $vendor = $record->vendor
            ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
            : 'Unknown Vendor';

        return "{$record->entry_number} • {$vendor}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var VendorLedgerEntry $record */
        return [
            'Vendor' => $record->vendor
                ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
                : '—',
            'Document' => trim(implode(' • ', array_filter([
                $record->document_type,
                $record->document_number,
            ]))),
            'Posting Date' => $record->posting_date?->toDateString() ?? '—',
            'Amount' => Number::currency((float) $record->amount, $record->currency_code ?? config('app.default_currency', 'USD')),
        ];
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
            'view' => ViewVendorLedgerEntry::route('/{record}'),
        ];
    }
}

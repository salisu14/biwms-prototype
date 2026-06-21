<?php

namespace App\Filament\Resources\CustomerLedgerEntries;

use App\Filament\Resources\CustomerLedgerEntries\Pages\ListCustomerLedgerEntries;
use App\Filament\Resources\CustomerLedgerEntries\Pages\ViewCustomerLedgerEntry;
use App\Filament\Resources\CustomerLedgerEntries\Schemas\CustomerLedgerEntryForm;
use App\Filament\Resources\CustomerLedgerEntries\Schemas\CustomerLedgerEntryInfolist;
use App\Filament\Resources\CustomerLedgerEntries\Tables\CustomerLedgerEntriesTable;
use App\Models\CustomerLedgerEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class CustomerLedgerEntryResource extends Resource
{
    protected static ?string $model = CustomerLedgerEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    protected static ?int $globalSearchSort = 180;

    public static function form(Schema $schema): Schema
    {
        return CustomerLedgerEntryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CustomerLedgerEntryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerLedgerEntriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer', 'currency']);
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
            'view' => ViewCustomerLedgerEntry::route('/{record}'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof CustomerLedgerEntry) {
            return static::getModelLabel();
        }

        $customer = $record->customer
            ? "{$record->customer->customer_number} - {$record->customer->name}"
            : 'Unknown Customer';

        return "{$record->entry_number} - {$customer}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'entry_number',
            'customer.customer_number',
            'customer.name',
            'document_number',
            'external_document_number',
            'description',
            'amount',
            'remaining_amount',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var CustomerLedgerEntry $record */
        $customer = $record->customer
            ? "{$record->customer->customer_number} - {$record->customer->name}"
            : 'Unknown Customer';

        return "{$record->entry_number} • {$customer}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var CustomerLedgerEntry $record */
        return [
            'Customer' => $record->customer
                ? "{$record->customer->customer_number} - {$record->customer->name}"
                : '—',
            'Document' => trim(implode(' • ', array_filter([
                $record->document_type,
                $record->document_number,
            ]))),
            'Posting Date' => $record->posting_date?->toDateString() ?? '—',
            'Amount' => Number::currency((float) $record->amount, $record->currency_code ?? config('app.default_currency', 'USD')),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

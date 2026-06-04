<?php

namespace App\Filament\Resources\PurchaseQuotes;

use App\Filament\Resources\PurchaseQuotes\Pages\CreatePurchaseQuote;
use App\Filament\Resources\PurchaseQuotes\Pages\EditPurchaseQuote;
use App\Filament\Resources\PurchaseQuotes\Pages\ListPurchaseQuotes;
use App\Filament\Resources\PurchaseQuotes\Pages\ViewPurchaseQuote;
use App\Filament\Resources\PurchaseQuotes\Schemas\PurchaseQuoteForm;
use App\Filament\Resources\PurchaseQuotes\Schemas\PurchaseQuoteInfolist;
use App\Filament\Resources\PurchaseQuotes\Tables\PurchaseQuotesTable;
use App\Models\PurchaseQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseQuoteResource extends Resource
{
    protected static ?string $model = PurchaseQuote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return PurchaseQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseQuotesTable::configure($table);
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
            'index' => ListPurchaseQuotes::route('/'),
            'create' => CreatePurchaseQuote::route('/create'),
            'view' => ViewPurchaseQuote::route('/{record}'),
            'edit' => EditPurchaseQuote::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PurchaseQuote) {
            return static::getModelLabel();
        }

        $vendor = $record->vendor
            ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
            : 'Unknown Vendor';

        return "{$record->document_no} - {$vendor}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_no',
            'vendor.vendor_code',
            'vendor.vendor_name',
            'vendor_quote_no',
            'buyer.name',
            'status',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PurchaseQuote $record */
        return [
            'Vendor' => $record->vendor
                ? "{$record->vendor->vendor_code} - {$record->vendor->vendor_name}"
                : '—',
            'Buyer' => $record->buyer?->name ?? '—',
            'Status' => $record->status?->label() ?? (string) $record->status,
            'Total' => number_format((float) $record->amount_including_vat, 2),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['vendor', 'buyer']);
    }
}

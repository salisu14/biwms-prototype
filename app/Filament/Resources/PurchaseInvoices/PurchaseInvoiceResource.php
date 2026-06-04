<?php

namespace App\Filament\Resources\PurchaseInvoices;

use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Resources\PurchaseInvoices\Pages\ViewPostedPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\ViewPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Filament\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceInfolist;
use App\Filament\Resources\PurchaseInvoices\Tables\PurchaseInvoicesTable;
use App\Models\PurchaseInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return PurchaseInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseInvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return ! $record->isPosted();
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->isPosted();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'view' => ViewPurchaseInvoice::route('/{record}'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
            'posted' => Pages\PostedPurchaseInvoices::route('/history/posted'),
            'view-posted' => ViewPostedPurchaseInvoice::route('/history/posted/{record}'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PurchaseInvoice) {
            return static::getModelLabel();
        }

        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');

        return "{$record->document_number} - {$vendor}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PurchaseInvoice $record */
        return [
            'Vendor' => $record->vendor_name ?: ($record->vendor?->vendor_name ?? '—'),
            'Purchase Order' => $record->order_number ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Location' => $record->location?->name ?? '—',
            'Total' => number_format((float) $record->grand_total, 2).' '.($record->currency_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['vendor', 'purchaseOrder', 'location'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'external_document_number',
            'order_number',
            'vendor_name',
            'vendor.vendor_code',
            'status',
            'location.name',
        ];
    }
}

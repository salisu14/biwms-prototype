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
use Illuminate\Support\Number;

class PurchaseInvoiceResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'procurement';
    }

    public static function permissionResource(): string
    {
        return 'purchase_invoice';
    }

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'vendor',
            'purchaseOrder',
            'location',
            'capExProject',
            'payableAccount',
            'requester',
            'approver',
            'poster',
        ]);
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

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PurchaseInvoice) {
            return static::getModelLabel();
        }

        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');

        return "{$record->document_number} - {$vendor}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'external_document_number',
            'order_number',
            'vendor_name',
            'vendor.vendor_code',
            'vendor.vendor_name',
            'status',
            'location.code',
            'location.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var PurchaseInvoice $record */
        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');

        return "{$record->document_number} - {$vendor}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PurchaseInvoice $record */
        return [
            'Vendor' => $record->vendor?->vendor_code
                ? "{$record->vendor->vendor_code} - ".($record->vendor_name ?: ($record->vendor?->vendor_name ?? '—'))
                : ($record->vendor_name ?: ($record->vendor?->vendor_name ?? '—')),
            'Purchase Order' => $record->order_number ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Location' => $record->location?->code
                ? "{$record->location->code} - {$record->location->name}"
                : ($record->location?->name ?? '—'),
            'Total' => Number::currency((float) $record->grand_total, $record->currency_code ?: config('app.default_currency', 'USD')),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'vendor',
            'purchaseOrder',
            'location',
            'capExProject',
            'payableAccount',
            'requester',
            'approver',
            'poster',
        ])->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
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
}

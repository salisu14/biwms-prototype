<?php

namespace App\Filament\Resources\VendorInvoices;

use App\Filament\Resources\VendorInvoices\Pages\CreateVendorInvoice;
use App\Filament\Resources\VendorInvoices\Pages\EditVendorInvoice;
use App\Filament\Resources\VendorInvoices\Pages\ListVendorInvoices;
use App\Filament\Resources\VendorInvoices\Pages\ViewVendorInvoice;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceForm;
use App\Filament\Resources\VendorInvoices\Schemas\VendorInvoiceInfolist;
use App\Filament\Resources\VendorInvoices\Tables\VendorInvoicesTable;
use App\Models\VendorInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Number;

class VendorInvoiceResource extends Resource
{
    protected static ?string $model = VendorInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = null;

    public static function form(Schema $schema): Schema
    {
        return VendorInvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VendorInvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorInvoicesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'vendor',
            'capExProject',
            'payableAccount',
            'requester',
            'approver',
            'postedByUser',
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof VendorInvoice) {
            return static::getModelLabel();
        }

        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');

        return "{$record->document_number} - {$vendor}";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'external_document_no',
            'vendor_invoice_no',
            'vendor_name',
            'vendor.vendor_code',
            'vendor.vendor_name',
            'status',
            'posted',
            'capExProject.description',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var VendorInvoice $record */
        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');

        return "{$record->document_number} - {$vendor}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var VendorInvoice $record */
        return [
            'Vendor' => $record->vendor?->vendor_code
                ? "{$record->vendor->vendor_code} - ".($record->vendor_name ?: ($record->vendor?->vendor_name ?? '—'))
                : ($record->vendor_name ?: ($record->vendor?->vendor_name ?? '—')),
            'Vendor Invoice' => $record->vendor_invoice_no ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Posted' => $record->posted ? 'Yes' : 'No',
            'Total' => Number::currency((float) $record->amount_including_tax, $record->currency_code ?: config('app.default_currency', 'USD')),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'vendor',
            'capExProject',
            'payableAccount',
            'requester',
            'approver',
            'postedByUser',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorInvoices::route('/'),
            'create' => CreateVendorInvoice::route('/create'),
            'view' => ViewVendorInvoice::route('/{record}'),
            'edit' => EditVendorInvoice::route('/{record}/edit'),
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

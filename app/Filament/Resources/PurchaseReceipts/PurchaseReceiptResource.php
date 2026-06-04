<?php

namespace App\Filament\Resources\PurchaseReceipts;

use App\Filament\Resources\PurchaseReceipts\Pages\CreatePurchaseReceipt;
use App\Filament\Resources\PurchaseReceipts\Pages\EditPurchaseReceipt;
use App\Filament\Resources\PurchaseReceipts\Pages\ListPurchaseReceipts;
use App\Filament\Resources\PurchaseReceipts\Pages\ViewPurchaseReceipt;
use App\Filament\Resources\PurchaseReceipts\Schemas\PurchaseReceiptForm;
use App\Filament\Resources\PurchaseReceipts\Schemas\PurchaseReceiptInfolist;
use App\Filament\Resources\PurchaseReceipts\Tables\PurchaseReceiptsTable;
use App\Models\PurchaseReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function form(Schema $schema): Schema
    {
        return PurchaseReceiptForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseReceiptInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseReceiptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'external_document_no',
            'purchase_order_no',
            'vendor_shipment_no',
            'vendor_invoice_no',
            'buy_from_vendor_name',
            'ship_to_name',
            'location_code',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var PurchaseReceipt $record */
        return [
            'Vendor' => $record->buy_from_vendor_name ?: '—',
            'Purchase Order' => $record->purchase_order_no ?: '—',
            'Status' => $record->status ?: '—',
            'Location' => $record->receivingLocation?->name ?: ($record->location_code ?: '—'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with('receivingLocation')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PurchaseReceipt) {
            return static::getModelLabel();
        }

        $vendor = $record->buy_from_vendor_name ?: 'Unknown Vendor';

        return "{$record->document_number} - {$vendor}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseReceipts::route('/'),
            'create' => CreatePurchaseReceipt::route('/create'),
            'view' => ViewPurchaseReceipt::route('/{record}'),
            'edit' => EditPurchaseReceipt::route('/{record}/edit'),
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

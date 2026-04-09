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
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseReceiptResource extends Resource
{
    protected static ?string $model = PurchaseReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
            //
        ];
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

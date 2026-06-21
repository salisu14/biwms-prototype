<?php

namespace App\Filament\Resources\BlanketPurchaseOrders;

use App\Filament\Resources\BlanketPurchaseOrders\Pages\CreateBlanketPurchaseOrder;
use App\Filament\Resources\BlanketPurchaseOrders\Pages\EditBlanketPurchaseOrder;
use App\Filament\Resources\BlanketPurchaseOrders\Pages\ListBlanketPurchaseOrders;
use App\Filament\Resources\BlanketPurchaseOrders\Schemas\BlanketPurchaseOrderForm;
use App\Filament\Resources\BlanketPurchaseOrders\Tables\BlanketPurchaseOrdersTable;
use App\Filament\Resources\Shared\RelationManagers\BlanketOrderLineRelationManager;
use App\Models\BlanketOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BlanketPurchaseOrderResource extends Resource
{
    protected static ?string $model = BlanketOrder::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Purchasing';

    protected static ?string $navigationLabel = 'Blanket Purchase Orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->purchase();
    }

    public static function form(Schema $schema): Schema
    {
        return BlanketPurchaseOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlanketPurchaseOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BlanketOrderLineRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlanketPurchaseOrders::route('/'),
            'create' => CreateBlanketPurchaseOrder::route('/create'),
            'edit' => EditBlanketPurchaseOrder::route('/{record}/edit'),
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

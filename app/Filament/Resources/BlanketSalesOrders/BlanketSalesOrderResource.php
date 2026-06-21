<?php

namespace App\Filament\Resources\BlanketSalesOrders;

use App\Filament\Resources\BlanketSalesOrders\Pages\CreateBlanketSalesOrder;
use App\Filament\Resources\BlanketSalesOrders\Pages\EditBlanketSalesOrder;
use App\Filament\Resources\BlanketSalesOrders\Pages\ListBlanketSalesOrders;
use App\Filament\Resources\BlanketSalesOrders\Schemas\BlanketSalesOrderForm;
use App\Filament\Resources\BlanketSalesOrders\Tables\BlanketSalesOrdersTable;
use App\Filament\Resources\Shared\RelationManagers\BlanketOrderLineRelationManager;
use App\Models\BlanketOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BlanketSalesOrderResource extends Resource
{
    protected static ?string $model = BlanketOrder::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Blanket Sales Orders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->sales();
    }

    public static function form(Schema $schema): Schema
    {
        return BlanketSalesOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BlanketSalesOrdersTable::configure($table);
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
            'index' => ListBlanketSalesOrders::route('/'),
            'create' => CreateBlanketSalesOrder::route('/create'),
            'edit' => EditBlanketSalesOrder::route('/{record}/edit'),
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

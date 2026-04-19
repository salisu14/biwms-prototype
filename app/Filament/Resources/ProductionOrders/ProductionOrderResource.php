<?php

namespace App\Filament\Resources\ProductionOrders;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Pages\CreateProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\EditProductionOrder;
use App\Filament\Resources\ProductionOrders\Pages\ListProductionOrders;
use App\Filament\Resources\ProductionOrders\Pages\ViewProductionOrder;
use App\Filament\Resources\ProductionOrders\Schemas\ProductionOrderForm;
use App\Filament\Resources\ProductionOrders\Schemas\ProductionOrderInfolist;
use App\Filament\Resources\ProductionOrders\Tables\ProductionOrdersTable;
use App\Models\Manufacturing\ProductionOrder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing';

    protected static ?string $label = 'Planned Production Order';

    protected static ?string $pluralLabel = 'Planned Production Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ProductionOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionOrdersTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('production_orders.status', [
                ProductionOrderStatus::RELEASED->value,
                ProductionOrderStatus::FINISHED->value,
            ]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotIn('production_orders.status', [
            ProductionOrderStatus::RELEASED->value,
            ProductionOrderStatus::FINISHED->value,
        ]);
    }

    public static function getRelations(): array
    {
        return [
            'lines' => RelationManagers\ProductionOrderLineRelationManager::class,
            'components' => RelationManagers\ComponentsRelationManager::class,
            'routing' => RelationManagers\RoutingRelationManager::class,
            'glEntries' => RelationManagers\GlEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionOrders::route('/'),
            'create' => CreateProductionOrder::route('/create'),
            'view' => ViewProductionOrder::route('/{record}'),
            'edit' => EditProductionOrder::route('/{record}/edit'),
        ];
    }
}

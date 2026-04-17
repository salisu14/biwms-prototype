<?php

namespace App\Filament\Resources\ProductionOrders;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Schemas\ProductionOrderForm;
use App\Filament\Resources\ProductionOrders\Schemas\ProductionOrderInfolist;
use App\Filament\Resources\ProductionOrders\Tables\ProductionOrdersTable;
use App\Models\Manufacturing\ProductionOrder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReleasedProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play';

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing';

    protected static ?string $label = 'Released Production Order';

    protected static ?string $pluralLabel = 'Released Production Orders';

    protected static ?string $slug = 'released-production-orders';

    protected static ?int $navigationSort = 2;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('production_orders.status', ProductionOrderStatus::RELEASED->value));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('production_orders.status', ProductionOrderStatus::RELEASED->value);
    }

    public static function getRelations(): array
    {
        return ProductionOrderResource::getRelations();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return true;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReleasedProductionOrders::route('/'),
            'view' => Pages\ViewReleasedProductionOrder::route('/{record}'),
            'edit' => Pages\EditReleasedProductionOrder::route('/{record}/edit'),
        ];
    }
}

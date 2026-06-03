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
use Illuminate\Database\Eloquent\Model;

class FinishedProductionOrderResource extends Resource
{
    protected static ?string $model = ProductionOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing';

    protected static ?string $label = 'Finished Production Order';

    protected static ?string $pluralLabel = 'Finished Production Orders';

    protected static ?string $slug = 'finished-production-orders';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'document_number';

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
            ->modifyQueryUsing(fn (Builder $query) => $query->where('production_orders.status', ProductionOrderStatus::FINISHED->value));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('production_orders.status', ProductionOrderStatus::FINISHED->value);
    }

    public static function getRelations(): array
    {
        return ProductionOrderResource::getRelations();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ProductionOrderResource::getGloballySearchableAttributes();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ProductionOrderResource::getGlobalSearchResultDetails($record);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery()->with([
            'item',
            'location',
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinishedProductionOrders::route('/'),
            'view' => Pages\ViewFinishedProductionOrder::route('/{record}'),
        ];
    }
}

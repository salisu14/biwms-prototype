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
use Illuminate\Database\Eloquent\Model;

class ProductionOrderResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'factory';
    }

    public static function permissionResource(): string
    {
        return 'production_order';
    }

    protected static ?string $model = ProductionOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Manufacturing';

    protected static ?string $label = 'Planned Production Order';

    protected static ?string $pluralLabel = 'Planned Production Orders';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'document_number';

    protected static ?int $globalSearchSort = -290;

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

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('factory.production_order.planned.view_any') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
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

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'document_number',
            'source_no',
            'description',
            'item.item_code',
            'item.description',
            'location.name',
            'location_code',
            'status',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ProductionOrder $record */
        return [
            'Item' => $record->item?->description ?: '—',
            'Source No.' => $record->source_no ?: '—',
            'Status' => $record->status?->value ?? '—',
            'Quantity' => number_format((float) $record->quantity, 2).' '.($record->unit_of_measure_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with([
            'item',
            'location',
        ]);
    }

    public static function modifyGlobalSearchQuery(Builder $query, string $search): void
    {
        $qualifiedDocumentNumber = $query->qualifyColumn('document_number');
        $qualifiedSourceNumber = $query->qualifyColumn('source_no');

        $query->orderByRaw(
            "case
                when lower({$qualifiedDocumentNumber}::text) = lower(?) then 0
                when lower({$qualifiedSourceNumber}::text) = lower(?) then 1
                when lower({$qualifiedDocumentNumber}::text) like lower(?) then 2
                when lower({$qualifiedSourceNumber}::text) like lower(?) then 3
                else 4
            end",
            [$search, $search, "%{$search}%", "%{$search}%"],
        )->orderByDesc($qualifiedDocumentNumber);
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

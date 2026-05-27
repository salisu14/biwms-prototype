<?php

namespace App\Filament\Sales\Resources\SalesOrders;

use App\Filament\Resources\SalesOrders\RelationManagers\GlEntriesRelationManager;
use App\Filament\Resources\SalesOrders\RelationManagers\LinesRelationManager;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderForm;
use App\Filament\Resources\SalesOrders\Schemas\SalesOrderInfolist;
use App\Filament\Resources\SalesOrders\Tables\SalesOrdersTable;
use App\Filament\Sales\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Sales\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Sales\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Sales\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Models\SalesOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesOrderResource extends Resource
{
    protected static ?string $model = SalesOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|null|\UnitEnum $navigationGroup = 'Sales Documents';

    protected static ?int $navigationSort = 1;

    // 🔑 CRITICAL: Role-based access for this resource
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('viewAny', SalesOrder::class);
    }

    public static function form(Schema $schema): Schema
    {
        return SalesOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesOrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
            GlEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesOrders::route('/'),
            'create' => CreateSalesOrder::route('/create'),
            'view' => ViewSalesOrder::route('/{record}'),
            'edit' => EditSalesOrder::route('/{record}/edit'),
        ];
    }
}

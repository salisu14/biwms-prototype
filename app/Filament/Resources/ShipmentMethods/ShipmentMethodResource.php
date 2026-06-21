<?php

namespace App\Filament\Resources\ShipmentMethods;

use App\Filament\Resources\ShipmentMethods\Pages\CreateShipmentMethod;
use App\Filament\Resources\ShipmentMethods\Pages\EditShipmentMethod;
use App\Filament\Resources\ShipmentMethods\Pages\ListShipmentMethods;
use App\Filament\Resources\ShipmentMethods\Pages\ViewShipmentMethod;
use App\Filament\Resources\ShipmentMethods\Schemas\ShippingMethodForm;
use App\Filament\Resources\ShipmentMethods\Schemas\ShippingMethodInfolist;
use App\Filament\Resources\ShipmentMethods\Tables\ShippingMethodsTable;
use App\Models\ShipmentMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShipmentMethodResource extends Resource
{
    protected static ?string $model = ShipmentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ShippingMethodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShippingMethodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingMethodsTable::configure($table);
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
            'index' => ListShipmentMethods::route('/'),
            'create' => CreateShipmentMethod::route('/create'),
            'view' => ViewShipmentMethod::route('/{record}'),
            'edit' => EditShipmentMethod::route('/{record}/edit'),
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

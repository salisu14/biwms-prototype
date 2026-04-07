<?php

namespace App\Filament\Resources\SalesShipmentHeaders;

use App\Filament\Resources\SalesShipmentHeaders\Pages\CreateSalesShipmentHeader;
use App\Filament\Resources\SalesShipmentHeaders\Pages\EditSalesShipmentHeader;
use App\Filament\Resources\SalesShipmentHeaders\Pages\ListSalesShipmentHeaders;
use App\Filament\Resources\SalesShipmentHeaders\Pages\ViewSalesShipmentHeader;
use App\Filament\Resources\SalesShipmentHeaders\Schemas\SalesShipmentHeaderForm;
use App\Filament\Resources\SalesShipmentHeaders\Schemas\SalesShipmentHeaderInfolist;
use App\Filament\Resources\SalesShipmentHeaders\Tables\SalesShipmentHeadersTable;
use App\Models\SalesShipmentHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalesShipmentHeaderResource extends Resource
{
    protected static ?string $model = SalesShipmentHeader::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SalesShipmentHeaderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalesShipmentHeaderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesShipmentHeadersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesShipmentHeaders::route('/'),
            'create' => CreateSalesShipmentHeader::route('/create'),
            'view' => ViewSalesShipmentHeader::route('/{record}'),
            'edit' => EditSalesShipmentHeader::route('/{record}/edit'),

            'posted' => Pages\PostedShipments::route('/history/posted-shipments'),
        ];
    }
}

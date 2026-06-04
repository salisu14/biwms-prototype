<?php

namespace App\Filament\Resources\PostedShipments;

use App\Filament\Resources\PostedShipments\Pages\ListPostedShipments;
use App\Filament\Resources\PostedShipments\Pages\ViewPostedShipment;
use App\Filament\Resources\PostedShipments\Schemas\PostedShipmentForm;
use App\Filament\Resources\PostedShipments\Schemas\PostedShipmentInfolist;
use App\Filament\Resources\PostedShipments\Tables\PostedShipmentsTable;
use App\Models\SalesShipmentHeader;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PostedShipmentResource extends Resource
{
    // Change this line to point to your actual Model
    protected static ?string $model = SalesShipmentHeader::class;

    // Hide it from the sidebar so users MUST go through the History page
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PostedShipmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PostedShipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostedShipmentsTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
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
            'index' => ListPostedShipments::route('/'),
            'view' => ViewPostedShipment::route('/{record}'),
        ];
    }
}

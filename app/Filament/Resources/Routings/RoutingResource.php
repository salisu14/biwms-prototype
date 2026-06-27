<?php

namespace App\Filament\Resources\Routings;

use App\Filament\Resources\Routings\Pages\CreateRouting;
use App\Filament\Resources\Routings\Pages\EditRouting;
use App\Filament\Resources\Routings\Pages\ListRoutings;
use App\Filament\Resources\Routings\Pages\ViewRouting;
use App\Filament\Resources\Routings\RelationManagers\RoutingLinesRelationManager;
use App\Filament\Resources\Routings\Schemas\RoutingForm;
use App\Filament\Resources\Routings\Schemas\RoutingInfolist;
use App\Filament\Resources\Routings\Tables\RoutingsTable;
use App\Models\Manufacturing\Routing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoutingResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'factory';
    }

    public static function permissionResource(): string
    {
        return 'routing';
    }

    protected static ?string $model = Routing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return RoutingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoutingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoutingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RoutingLinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoutings::route('/'),
            'create' => CreateRouting::route('/create'),
            'view' => ViewRouting::route('/{record}'),
            'edit' => EditRouting::route('/{record}/edit'),
        ];
    }
}

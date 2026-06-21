<?php

namespace App\Filament\Resources\RoutingVersions;

use App\Filament\Resources\RoutingVersions\Pages\CreateRoutingVersion;
use App\Filament\Resources\RoutingVersions\Pages\EditRoutingVersion;
use App\Filament\Resources\RoutingVersions\Pages\ListRoutingVersions;
use App\Filament\Resources\RoutingVersions\Pages\ViewRoutingVersion;
use App\Filament\Resources\RoutingVersions\Schemas\RoutingVersionForm;
use App\Filament\Resources\RoutingVersions\Schemas\RoutingVersionInfolist;
use App\Filament\Resources\RoutingVersions\Tables\RoutingVersionsTable;
use App\Models\Manufacturing\RoutingVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoutingVersionResource extends Resource
{
    protected static ?string $model = RoutingVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return RoutingVersionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoutingVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoutingVersionsTable::configure($table);
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
            'index' => ListRoutingVersions::route('/'),
            'create' => CreateRoutingVersion::route('/create'),
            'view' => ViewRoutingVersion::route('/{record}'),
            'edit' => EditRoutingVersion::route('/{record}/edit'),
        ];
    }
}

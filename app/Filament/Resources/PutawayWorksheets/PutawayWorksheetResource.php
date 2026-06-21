<?php

namespace App\Filament\Resources\PutawayWorksheets;

use App\Filament\Resources\PutawayWorksheets\Pages\CreatePutawayWorksheet;
use App\Filament\Resources\PutawayWorksheets\Pages\EditPutawayWorksheet;
use App\Filament\Resources\PutawayWorksheets\Pages\ListPutawayWorksheets;
use App\Filament\Resources\PutawayWorksheets\Pages\ViewPutawayWorksheet;
use App\Filament\Resources\PutawayWorksheets\Schemas\PutawayWorksheetForm;
use App\Filament\Resources\PutawayWorksheets\Schemas\PutawayWorksheetInfolist;
use App\Filament\Resources\PutawayWorksheets\Tables\PutawayWorksheetsTable;
use App\Models\PutawayWorksheet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PutawayWorksheetResource extends Resource
{
    protected static ?string $model = PutawayWorksheet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PutawayWorksheetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PutawayWorksheetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PutawayWorksheetsTable::configure($table);
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
            'index' => ListPutawayWorksheets::route('/'),
            'create' => CreatePutawayWorksheet::route('/create'),
            'view' => ViewPutawayWorksheet::route('/{record}'),
            'edit' => EditPutawayWorksheet::route('/{record}/edit'),
        ];
    }
}

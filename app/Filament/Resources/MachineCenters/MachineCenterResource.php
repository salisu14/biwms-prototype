<?php

namespace App\Filament\Resources\MachineCenters;

use App\Filament\Resources\MachineCenters\Pages\CreateMachineCenter;
use App\Filament\Resources\MachineCenters\Pages\EditMachineCenter;
use App\Filament\Resources\MachineCenters\Pages\ListMachineCenters;
use App\Filament\Resources\MachineCenters\Pages\ViewMachineCenter;
use App\Filament\Resources\MachineCenters\Schemas\MachineCenterForm;
use App\Filament\Resources\MachineCenters\Schemas\MachineCenterInfolist;
use App\Filament\Resources\MachineCenters\Tables\MachineCentersTable;
use App\Models\Manufacturing\MachineCenter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MachineCenterResource extends Resource
{
    protected static ?string $model = MachineCenter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MachineCenterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MachineCenterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MachineCentersTable::configure($table);
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
            'index' => ListMachineCenters::route('/'),
            'create' => CreateMachineCenter::route('/create'),
            'view' => ViewMachineCenter::route('/{record}'),
            'edit' => EditMachineCenter::route('/{record}/edit'),
        ];
    }
}

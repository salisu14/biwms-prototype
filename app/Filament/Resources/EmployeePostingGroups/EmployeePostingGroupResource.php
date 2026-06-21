<?php

namespace App\Filament\Resources\EmployeePostingGroups;

use App\Filament\Resources\EmployeePostingGroups\Pages\CreateEmployeePostingGroup;
use App\Filament\Resources\EmployeePostingGroups\Pages\EditEmployeePostingGroup;
use App\Filament\Resources\EmployeePostingGroups\Pages\ListEmployeePostingGroups;
use App\Filament\Resources\EmployeePostingGroups\Schemas\EmployeePostingGroupForm;
use App\Filament\Resources\EmployeePostingGroups\Tables\EmployeePostingGroupsTable;
use App\Models\EmployeePostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeePostingGroupResource extends Resource
{
    protected static ?string $model = EmployeePostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return EmployeePostingGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeePostingGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EmployeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeePostingGroups::route('/'),
            'create' => CreateEmployeePostingGroup::route('/create'),
            'edit' => EditEmployeePostingGroup::route('/{record}/edit'),
        ];
    }
}

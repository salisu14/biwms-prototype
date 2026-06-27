<?php

namespace App\Filament\Resources\GeneralBusinessPostingGroups;

use App\Filament\Resources\GeneralBusinessPostingGroups\Pages\CreateGeneralBusinessPostingGroup;
use App\Filament\Resources\GeneralBusinessPostingGroups\Pages\EditGeneralBusinessPostingGroup;
use App\Filament\Resources\GeneralBusinessPostingGroups\Pages\ListGeneralBusinessPostingGroups;
use App\Filament\Resources\GeneralBusinessPostingGroups\Schemas\GeneralBusinessPostingGroupForm;
use App\Filament\Resources\GeneralBusinessPostingGroups\Tables\GeneralBusinessPostingGroupsTable;
use App\Models\GeneralBusinessPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GeneralBusinessPostingGroupResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'general_business_posting_group';
    }

    protected static ?string $model = GeneralBusinessPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return GeneralBusinessPostingGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralBusinessPostingGroupsTable::configure($table);
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
            'index' => ListGeneralBusinessPostingGroups::route('/'),
            'create' => CreateGeneralBusinessPostingGroup::route('/create'),
            'edit' => EditGeneralBusinessPostingGroup::route('/{record}/edit'),
        ];
    }
}

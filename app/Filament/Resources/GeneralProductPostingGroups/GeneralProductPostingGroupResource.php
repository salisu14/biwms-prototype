<?php

namespace App\Filament\Resources\GeneralProductPostingGroups;

use App\Filament\Resources\GeneralProductPostingGroups\Pages\CreateGeneralProductPostingGroup;
use App\Filament\Resources\GeneralProductPostingGroups\Pages\EditGeneralProductPostingGroup;
use App\Filament\Resources\GeneralProductPostingGroups\Pages\ListGeneralProductPostingGroups;
use App\Filament\Resources\GeneralProductPostingGroups\Pages\ViewGeneralProductPostingGroup;
use App\Filament\Resources\GeneralProductPostingGroups\Schemas\GeneralProductPostingGroupForm;
use App\Filament\Resources\GeneralProductPostingGroups\Tables\GeneralProductPostingGroupsTable;
use App\Models\GeneralProductPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GeneralProductPostingGroupResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'general_product_posting_group';
    }

    protected static ?string $model = GeneralProductPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return GeneralProductPostingGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralProductPostingGroupsTable::configure($table);
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
            'index' => ListGeneralProductPostingGroups::route('/'),
            'create' => CreateGeneralProductPostingGroup::route('/create'),
            'edit' => EditGeneralProductPostingGroup::route('/{record}/edit'),
            'view' => ViewGeneralProductPostingGroup::route('/{record}'), //
        ];
    }
}

<?php

namespace App\Filament\Resources\PricingGroups;

use App\Filament\Resources\PricingGroups\Pages\CreatePricingGroup;
use App\Filament\Resources\PricingGroups\Pages\EditPricingGroup;
use App\Filament\Resources\PricingGroups\Pages\ListPricingGroups;
use App\Filament\Resources\PricingGroups\Pages\ViewPricingGroup;
use App\Filament\Resources\PricingGroups\Schemas\PricingGroupForm;
use App\Filament\Resources\PricingGroups\Schemas\PricingGroupInfolist;
use App\Filament\Resources\PricingGroups\Tables\PricingGroupsTable;
use App\Models\PricingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PricingGroupResource extends Resource
{
    protected static ?string $model = PricingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PricingGroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PricingGroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricingGroupsTable::configure($table);
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
            'index' => ListPricingGroups::route('/'),
            'create' => CreatePricingGroup::route('/create'),
            'view' => ViewPricingGroup::route('/{record}'),
            'edit' => EditPricingGroup::route('/{record}/edit'),
        ];
    }
}

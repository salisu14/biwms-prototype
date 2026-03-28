<?php

namespace App\Filament\Resources\GeneralPostingSetups;

use App\Filament\Resources\GeneralPostingSetups\Pages\CreateGeneralPostingSetup;
use App\Filament\Resources\GeneralPostingSetups\Pages\EditGeneralPostingSetup;
use App\Filament\Resources\GeneralPostingSetups\Pages\ListGeneralPostingSetups;
use App\Filament\Resources\GeneralPostingSetups\Pages\ViewGeneralPostingSetup;
use App\Filament\Resources\GeneralPostingSetups\Schemas\GeneralPostingSetupForm;
use App\Filament\Resources\GeneralPostingSetups\Schemas\GeneralPostingSetupInfolist;
use App\Filament\Resources\GeneralPostingSetups\Tables\GeneralPostingSetupsTable;
use App\Models\GeneralPostingSetup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GeneralPostingSetupResource extends Resource
{
    protected static ?string $model = GeneralPostingSetup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return GeneralPostingSetupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GeneralPostingSetupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeneralPostingSetupsTable::configure($table);
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
            'index' => ListGeneralPostingSetups::route('/'),
            'create' => CreateGeneralPostingSetup::route('/create'),
            'view' => ViewGeneralPostingSetup::route('/{record}'),
            'edit' => EditGeneralPostingSetup::route('/{record}/edit'),
        ];
    }
}

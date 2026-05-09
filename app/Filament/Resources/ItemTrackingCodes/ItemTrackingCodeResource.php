<?php

namespace App\Filament\Resources\ItemTrackingCodes;

use App\Filament\Resources\ItemTrackingCodes\Pages\CreateItemTrackingCode;
use App\Filament\Resources\ItemTrackingCodes\Pages\EditItemTrackingCode;
use App\Filament\Resources\ItemTrackingCodes\Pages\ListItemTrackingCodes;
use App\Filament\Resources\ItemTrackingCodes\Pages\ViewItemTrackingCode;
use App\Filament\Resources\ItemTrackingCodes\Schemas\ItemTrackingCodeForm;
use App\Filament\Resources\ItemTrackingCodes\Schemas\ItemTrackingCodeInfolist;
use App\Filament\Resources\ItemTrackingCodes\Tables\ItemTrackingCodesTable;
use App\Models\ItemTrackingCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemTrackingCodeResource extends Resource
{
    protected static ?string $model = ItemTrackingCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ItemTrackingCodeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemTrackingCodeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemTrackingCodesTable::configure($table);
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
            'index' => ListItemTrackingCodes::route('/'),
            'create' => CreateItemTrackingCode::route('/create'),
            'view' => ViewItemTrackingCode::route('/{record}'),
            'edit' => EditItemTrackingCode::route('/{record}/edit'),
        ];
    }
}

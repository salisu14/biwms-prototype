<?php

namespace App\Filament\Resources\ItemCharges;

use App\Filament\Resources\ItemCharges\Pages\CreateItemCharge;
use App\Filament\Resources\ItemCharges\Pages\EditItemCharge;
use App\Filament\Resources\ItemCharges\Pages\ListItemCharges;
use App\Filament\Resources\ItemCharges\Schemas\ItemChargeForm;
use App\Filament\Resources\ItemCharges\Tables\ItemChargesTable;
use App\Models\ItemCharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemChargeResource extends Resource
{
    protected static ?string $model = ItemCharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return ItemChargeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemChargesTable::configure($table);
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
            'index' => ListItemCharges::route('/'),
            'create' => CreateItemCharge::route('/create'),
            'edit' => EditItemCharge::route('/{record}/edit'),
        ];
    }
}

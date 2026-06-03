<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks;

use App\Filament\Resources\PricingMasterQuantityBreaks\Pages\CreatePricingMasterQuantityBreak;
use App\Filament\Resources\PricingMasterQuantityBreaks\Pages\EditPricingMasterQuantityBreak;
use App\Filament\Resources\PricingMasterQuantityBreaks\Pages\ListPricingMasterQuantityBreaks;
use App\Filament\Resources\PricingMasterQuantityBreaks\Schemas\PricingMasterQuantityBreakForm;
use App\Filament\Resources\PricingMasterQuantityBreaks\Tables\PricingMasterQuantityBreaksTable;
use App\Models\PricingMasterQuantityBreak;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PricingMasterQuantityBreakResource extends Resource
{
    protected static ?string $model = PricingMasterQuantityBreak::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PricingMasterQuantityBreakForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricingMasterQuantityBreaksTable::configure($table);
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
            'index' => ListPricingMasterQuantityBreaks::route('/'),
            'create' => CreatePricingMasterQuantityBreak::route('/create'),
            'edit' => EditPricingMasterQuantityBreak::route('/{record}/edit'),
        ];
    }
}

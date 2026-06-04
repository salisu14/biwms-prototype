<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks;

use App\Filament\Resources\PricingMasterQuantityBreaks\Pages\CreatePricingMasterQuantityBreak;
use App\Filament\Resources\PricingMasterQuantityBreaks\Pages\EditPricingMasterQuantityBreak;
use App\Filament\Resources\PricingMasterQuantityBreaks\Pages\ListPricingMasterQuantityBreaks;
use App\Filament\Resources\PricingMasterQuantityBreaks\Pages\ViewPricingMasterQuantityBreak;
use App\Filament\Resources\PricingMasterQuantityBreaks\Schemas\PricingMasterQuantityBreakForm;
use App\Filament\Resources\PricingMasterQuantityBreaks\Schemas\PricingMasterQuantityBreakInfolist;
use App\Filament\Resources\PricingMasterQuantityBreaks\Tables\PricingMasterQuantityBreaksTable;
use App\Models\PricingMasterQuantityBreak;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PricingMasterQuantityBreakResource extends Resource
{
    protected static ?string $model = PricingMasterQuantityBreak::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'line_number';

    public static function form(Schema $schema): Schema
    {
        return PricingMasterQuantityBreakForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PricingMasterQuantityBreakInfolist::configure($schema);
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

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'pricingMaster.price_list_code',
            'pricingMaster.description',
            'minimum_quantity',
            'maximum_quantity',
            'unit_of_measure_code',
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof PricingMasterQuantityBreak) {
            return static::getModelLabel();
        }

        $pricingMaster = $record->pricingMaster;
        $masterLabel = $pricingMaster
            ? "{$pricingMaster->price_list_code} - {$pricingMaster->description}"
            : 'Unknown Pricing Master';

        return "{$masterLabel} • Line {$record->line_number}";
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricingMasterQuantityBreaks::route('/'),
            'create' => CreatePricingMasterQuantityBreak::route('/create'),
            'view' => ViewPricingMasterQuantityBreak::route('/{record}'),
            'edit' => EditPricingMasterQuantityBreak::route('/{record}/edit'),
        ];
    }
}

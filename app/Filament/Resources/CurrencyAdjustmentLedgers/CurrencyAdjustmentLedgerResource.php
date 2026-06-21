<?php

namespace App\Filament\Resources\CurrencyAdjustmentLedgers;

use App\Filament\Resources\CurrencyAdjustmentLedgers\Pages\CreateCurrencyAdjustmentLedger;
use App\Filament\Resources\CurrencyAdjustmentLedgers\Pages\EditCurrencyAdjustmentLedger;
use App\Filament\Resources\CurrencyAdjustmentLedgers\Pages\ListCurrencyAdjustmentLedgers;
use App\Filament\Resources\CurrencyAdjustmentLedgers\Pages\ViewCurrencyAdjustmentLedger;
use App\Filament\Resources\CurrencyAdjustmentLedgers\Schemas\CurrencyAdjustmentLedgerForm;
use App\Filament\Resources\CurrencyAdjustmentLedgers\Schemas\CurrencyAdjustmentLedgerInfolist;
use App\Filament\Resources\CurrencyAdjustmentLedgers\Tables\CurrencyAdjustmentLedgersTable;
use App\Models\CurrencyAdjustmentLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CurrencyAdjustmentLedgerResource extends Resource
{
    protected static ?string $model = CurrencyAdjustmentLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CurrencyAdjustmentLedgerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CurrencyAdjustmentLedgerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CurrencyAdjustmentLedgersTable::configure($table);
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
            'index' => ListCurrencyAdjustmentLedgers::route('/'),
            'create' => CreateCurrencyAdjustmentLedger::route('/create'),
            'view' => ViewCurrencyAdjustmentLedger::route('/{record}'),
            'edit' => EditCurrencyAdjustmentLedger::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\CashReceiptLines;

use App\Filament\Resources\CashReceiptLines\Pages\CreateCashReceiptLine;
use App\Filament\Resources\CashReceiptLines\Pages\EditCashReceiptLine;
use App\Filament\Resources\CashReceiptLines\Pages\ListCashReceiptLines;
use App\Filament\Resources\CashReceiptLines\Pages\ViewCashReceiptLine;
use App\Filament\Resources\CashReceiptLines\Schemas\CashReceiptLineForm;
use App\Filament\Resources\CashReceiptLines\Schemas\CashReceiptLineInfolist;
use App\Filament\Resources\CashReceiptLines\Tables\CashReceiptLinesTable;
use App\Models\CashReceiptLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CashReceiptLineResource extends Resource
{
    protected static ?string $model = CashReceiptLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 23;

    protected static ?string $navigationLabel = 'Cash Receipt Journal';

    protected static ?string $recordTitleAttribute = 'customer_no';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return CashReceiptLineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CashReceiptLineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashReceiptLinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashReceiptLines::route('/'),
            'create' => CreateCashReceiptLine::route('/create'),
            'view' => ViewCashReceiptLine::route('/{record}'),
            'edit' => EditCashReceiptLine::route('/{record}/edit'),
        ];
    }
}

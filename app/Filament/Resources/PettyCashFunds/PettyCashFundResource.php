<?php

namespace App\Filament\Resources\PettyCashFunds;

use App\Filament\Resources\PettyCashFunds\Pages\CreatePettyCashFund;
use App\Filament\Resources\PettyCashFunds\Pages\EditPettyCashFund;
use App\Filament\Resources\PettyCashFunds\Pages\ListPettyCashFunds;
use App\Filament\Resources\PettyCashFunds\Pages\ViewPettyCashFund;
use App\Filament\Resources\PettyCashFunds\Schemas\PettyCashFundForm;
use App\Filament\Resources\PettyCashFunds\Schemas\PettyCashFundInfolist;
use App\Filament\Resources\PettyCashFunds\Tables\PettyCashFundsTable;
use App\Models\PettyCashFund;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PettyCashFundResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'petty_cash_funds';
    }

    public static function permissionResource(): string
    {
        return 'petty_cash_fund';
    }

    protected static ?string $model = PettyCashFund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole([
            'finance-manager',
            'finance-accountant',
            'super_admin',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PettyCashFundForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PettyCashFundInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PettyCashFundsTable::configure($table);
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
            'index' => ListPettyCashFunds::route('/'),
            'create' => CreatePettyCashFund::route('/create'),
            'view' => ViewPettyCashFund::route('/{record}'),
            'edit' => EditPettyCashFund::route('/{record}/edit'),
        ];
    }
}

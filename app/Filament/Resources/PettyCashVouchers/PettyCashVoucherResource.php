<?php

namespace App\Filament\Resources\PettyCashVouchers;

use App\Filament\Resources\PettyCashVouchers\Pages\CreatePettyCashVoucher;
use App\Filament\Resources\PettyCashVouchers\Pages\EditPettyCashVoucher;
use App\Filament\Resources\PettyCashVouchers\Pages\ListPettyCashVouchers;
use App\Filament\Resources\PettyCashVouchers\Pages\ViewPettyCashVoucher;
use App\Filament\Resources\PettyCashVouchers\Schemas\PettyCashVoucherForm;
use App\Filament\Resources\PettyCashVouchers\Schemas\PettyCashVoucherInfolist;
use App\Filament\Resources\PettyCashVouchers\Tables\PettyCashVouchersTable;
use App\Models\PettyCashVoucher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PettyCashVoucherResource extends Resource
{
    protected static ?string $model = PettyCashVoucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function canAccess(): bool
    {
        return auth()->user()->hasAnyRole([
            'finance-manager',
            'finance-accountant',
            'super_admin',
            'warehouse-manager',
            'storekeeper',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PettyCashVoucherForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PettyCashVoucherInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PettyCashVouchersTable::configure($table);
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
            'index' => ListPettyCashVouchers::route('/'),
            'create' => CreatePettyCashVoucher::route('/create'),
            'view' => ViewPettyCashVoucher::route('/{record}'),
            'edit' => EditPettyCashVoucher::route('/{record}/edit'),
        ];
    }
}

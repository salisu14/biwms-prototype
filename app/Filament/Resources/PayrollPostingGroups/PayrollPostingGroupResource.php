<?php

namespace App\Filament\Resources\PayrollPostingGroups;

use App\Filament\Resources\PayrollPostingGroups\Pages\CreatePayrollPostingGroup;
use App\Filament\Resources\PayrollPostingGroups\Pages\EditPayrollPostingGroup;
use App\Filament\Resources\PayrollPostingGroups\Pages\ListPayrollPostingGroups;
use App\Filament\Resources\PayrollPostingGroups\Schemas\PayrollPostingGroupForm;
use App\Filament\Resources\PayrollPostingGroups\Tables\PayrollPostingGroupsTable;
use App\Models\PayrollPostingGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayrollPostingGroupResource extends Resource
{
    protected static ?string $model = PayrollPostingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return PayrollPostingGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayrollPostingGroupsTable::configure($table);
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
            'index' => ListPayrollPostingGroups::route('/'),
            'create' => CreatePayrollPostingGroup::route('/create'),
            'edit' => EditPayrollPostingGroup::route('/{record}/edit'),
        ];
    }
}

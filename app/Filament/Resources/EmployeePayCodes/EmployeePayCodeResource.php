<?php

namespace App\Filament\Resources\EmployeePayCodes;

use App\Filament\Resources\EmployeePayCodes\Pages\CreateEmployeePayCode;
use App\Filament\Resources\EmployeePayCodes\Pages\EditEmployeePayCode;
use App\Filament\Resources\EmployeePayCodes\Pages\ListEmployeePayCodes;
use App\Filament\Resources\EmployeePayCodes\Schemas\EmployeePayCodeForm;
use App\Filament\Resources\EmployeePayCodes\Tables\EmployeePayCodesTable;
use App\Models\EmployeePayCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeePayCodeResource extends Resource
{
    protected static ?string $model = EmployeePayCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return EmployeePayCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeePayCodesTable::configure($table);
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
            'index' => ListEmployeePayCodes::route('/'),
            'create' => CreateEmployeePayCode::route('/create'),
            'edit' => EditEmployeePayCode::route('/{record}/edit'),
        ];
    }
}

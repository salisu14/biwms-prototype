<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeShifts;

use App\Filament\Resources\EmployeeShifts\Pages\CreateEmployeeShift;
use App\Filament\Resources\EmployeeShifts\Pages\EditEmployeeShift;
use App\Filament\Resources\EmployeeShifts\Pages\ListEmployeeShifts;
use App\Filament\Resources\EmployeeShifts\Pages\ViewEmployeeShift;
use App\Filament\Resources\EmployeeShifts\Schemas\EmployeeShiftForm;
use App\Filament\Resources\EmployeeShifts\Schemas\EmployeeShiftInfolist;
use App\Filament\Resources\EmployeeShifts\Tables\EmployeeShiftsTable;
use App\Models\EmployeeShift;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeShiftResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_shift';
    }

    protected static ?string $model = EmployeeShift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return EmployeeShiftForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeShiftInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeShiftsTable::configure($table);
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
            'index' => ListEmployeeShifts::route('/'),
            'create' => CreateEmployeeShift::route('/create'),
            'view' => ViewEmployeeShift::route('/{record}'),
            'edit' => EditEmployeeShift::route('/{record}/edit'),
        ];
    }
}

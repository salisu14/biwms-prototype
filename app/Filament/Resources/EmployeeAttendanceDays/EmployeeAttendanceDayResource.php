<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeAttendanceDays;

use App\Filament\Resources\EmployeeAttendanceDays\Pages\ListEmployeeAttendanceDays;
use App\Filament\Resources\EmployeeAttendanceDays\Pages\ViewEmployeeAttendanceDay;
use App\Filament\Resources\EmployeeAttendanceDays\Schemas\EmployeeAttendanceDayForm;
use App\Filament\Resources\EmployeeAttendanceDays\Schemas\EmployeeAttendanceDayInfolist;
use App\Filament\Resources\EmployeeAttendanceDays\Tables\EmployeeAttendanceDaysTable;
use App\Models\EmployeeAttendanceDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeAttendanceDayResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_register';
    }

    protected static ?string $model = EmployeeAttendanceDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Attendance Register';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return EmployeeAttendanceDayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeAttendanceDayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeAttendanceDaysTable::configure($table);
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
            'index' => ListEmployeeAttendanceDays::route('/'),
            'view' => ViewEmployeeAttendanceDay::route('/{record}'),
        ];
    }
}

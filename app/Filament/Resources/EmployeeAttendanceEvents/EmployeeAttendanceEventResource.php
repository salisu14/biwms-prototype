<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeAttendanceEvents;

use App\Filament\Resources\EmployeeAttendanceEvents\Pages\ListEmployeeAttendanceEvents;
use App\Filament\Resources\EmployeeAttendanceEvents\Pages\ViewEmployeeAttendanceEvent;
use App\Filament\Resources\EmployeeAttendanceEvents\Schemas\EmployeeAttendanceEventForm;
use App\Filament\Resources\EmployeeAttendanceEvents\Schemas\EmployeeAttendanceEventInfolist;
use App\Filament\Resources\EmployeeAttendanceEvents\Tables\EmployeeAttendanceEventsTable;
use App\Models\EmployeeAttendanceEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeAttendanceEventResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'attendance_event';
    }

    protected static ?string $model = EmployeeAttendanceEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Attendance History';

    protected static ?int $navigationSort = 100;

    public static function form(Schema $schema): Schema
    {
        return EmployeeAttendanceEventForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeAttendanceEventInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeAttendanceEventsTable::configure($table);
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
            'index' => ListEmployeeAttendanceEvents::route('/'),
            'view' => ViewEmployeeAttendanceEvent::route('/{record}'),
        ];
    }
}

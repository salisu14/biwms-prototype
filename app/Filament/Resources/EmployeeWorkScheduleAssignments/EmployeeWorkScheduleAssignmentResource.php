<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkScheduleAssignments;

use App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages\CreateEmployeeWorkScheduleAssignment;
use App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages\EditEmployeeWorkScheduleAssignment;
use App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages\ListEmployeeWorkScheduleAssignments;
use App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages\ViewEmployeeWorkScheduleAssignment;
use App\Filament\Resources\EmployeeWorkScheduleAssignments\Schemas\EmployeeWorkScheduleAssignmentForm;
use App\Filament\Resources\EmployeeWorkScheduleAssignments\Schemas\EmployeeWorkScheduleAssignmentInfolist;
use App\Filament\Resources\EmployeeWorkScheduleAssignments\Tables\EmployeeWorkScheduleAssignmentsTable;
use App\Models\EmployeeWorkScheduleAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeWorkScheduleAssignmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'work_schedule';
    }

    protected static ?string $model = EmployeeWorkScheduleAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Time & Attendance';

    protected static ?string $navigationLabel = 'Work Schedules';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return EmployeeWorkScheduleAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeWorkScheduleAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeWorkScheduleAssignmentsTable::configure($table);
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
            'index' => ListEmployeeWorkScheduleAssignments::route('/'),
            'create' => CreateEmployeeWorkScheduleAssignment::route('/create'),
            'view' => ViewEmployeeWorkScheduleAssignment::route('/{record}'),
            'edit' => EditEmployeeWorkScheduleAssignment::route('/{record}/edit'),
        ];
    }
}

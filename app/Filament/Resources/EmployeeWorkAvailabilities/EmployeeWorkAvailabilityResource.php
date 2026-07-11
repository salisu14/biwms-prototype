<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities;

use App\Filament\Resources\EmployeeWorkAvailabilities\Pages\CreateEmployeeWorkAvailability;
use App\Filament\Resources\EmployeeWorkAvailabilities\Pages\EditEmployeeWorkAvailability;
use App\Filament\Resources\EmployeeWorkAvailabilities\Pages\ListEmployeeWorkAvailabilities;
use App\Filament\Resources\EmployeeWorkAvailabilities\Pages\ViewEmployeeWorkAvailability;
use App\Filament\Resources\EmployeeWorkAvailabilities\Schemas\EmployeeWorkAvailabilityForm;
use App\Filament\Resources\EmployeeWorkAvailabilities\Schemas\EmployeeWorkAvailabilityInfolist;
use App\Filament\Resources\EmployeeWorkAvailabilities\Tables\EmployeeWorkAvailabilitiesTable;
use App\Models\EmployeeWorkAvailability;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeWorkAvailabilityResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'employee_work_availability';
    }

    protected static ?string $model = EmployeeWorkAvailability::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Employee Availability';

    public static function form(Schema $schema): Schema
    {
        return EmployeeWorkAvailabilityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmployeeWorkAvailabilityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeWorkAvailabilitiesTable::configure($table);
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
            'index' => ListEmployeeWorkAvailabilities::route('/'),
            'create' => CreateEmployeeWorkAvailability::route('/create'),
            'view' => ViewEmployeeWorkAvailability::route('/{record}'),
            'edit' => EditEmployeeWorkAvailability::route('/{record}/edit'),
        ];
    }
}

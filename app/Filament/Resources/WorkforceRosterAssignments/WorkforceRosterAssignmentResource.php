<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments;

use App\Filament\Resources\WorkforceRosterAssignments\Pages\CreateWorkforceRosterAssignment;
use App\Filament\Resources\WorkforceRosterAssignments\Pages\EditWorkforceRosterAssignment;
use App\Filament\Resources\WorkforceRosterAssignments\Pages\ListWorkforceRosterAssignments;
use App\Filament\Resources\WorkforceRosterAssignments\Pages\ViewWorkforceRosterAssignment;
use App\Filament\Resources\WorkforceRosterAssignments\Schemas\WorkforceRosterAssignmentForm;
use App\Filament\Resources\WorkforceRosterAssignments\Schemas\WorkforceRosterAssignmentInfolist;
use App\Filament\Resources\WorkforceRosterAssignments\Tables\WorkforceRosterAssignmentsTable;
use App\Models\WorkforceRosterAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceRosterAssignmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_roster_assignment';
    }

    protected static ?string $model = WorkforceRosterAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Shift Assignments';

    public static function form(Schema $schema): Schema
    {
        return WorkforceRosterAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceRosterAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceRosterAssignmentsTable::configure($table);
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
            'index' => ListWorkforceRosterAssignments::route('/'),
            'create' => CreateWorkforceRosterAssignment::route('/create'),
            'view' => ViewWorkforceRosterAssignment::route('/{record}'),
            'edit' => EditWorkforceRosterAssignment::route('/{record}/edit'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments;

use App\Filament\Resources\WorkforceRotationAssignments\Pages\CreateWorkforceRotationAssignment;
use App\Filament\Resources\WorkforceRotationAssignments\Pages\EditWorkforceRotationAssignment;
use App\Filament\Resources\WorkforceRotationAssignments\Pages\ListWorkforceRotationAssignments;
use App\Filament\Resources\WorkforceRotationAssignments\Pages\ViewWorkforceRotationAssignment;
use App\Filament\Resources\WorkforceRotationAssignments\Schemas\WorkforceRotationAssignmentForm;
use App\Filament\Resources\WorkforceRotationAssignments\Schemas\WorkforceRotationAssignmentInfolist;
use App\Filament\Resources\WorkforceRotationAssignments\Tables\WorkforceRotationAssignmentsTable;
use App\Models\WorkforceRotationAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceRotationAssignmentResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_rotation_assignment';
    }

    protected static ?string $model = WorkforceRotationAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Rotation Assignments';

    public static function form(Schema $schema): Schema
    {
        return WorkforceRotationAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceRotationAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceRotationAssignmentsTable::configure($table);
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
            'index' => ListWorkforceRotationAssignments::route('/'),
            'create' => CreateWorkforceRotationAssignment::route('/create'),
            'view' => ViewWorkforceRotationAssignment::route('/{record}'),
            'edit' => EditWorkforceRotationAssignment::route('/{record}/edit'),
        ];
    }
}

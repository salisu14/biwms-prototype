<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements;

use App\Filament\Resources\WorkforceStaffingRequirements\Pages\CreateWorkforceStaffingRequirement;
use App\Filament\Resources\WorkforceStaffingRequirements\Pages\EditWorkforceStaffingRequirement;
use App\Filament\Resources\WorkforceStaffingRequirements\Pages\ListWorkforceStaffingRequirements;
use App\Filament\Resources\WorkforceStaffingRequirements\Pages\ViewWorkforceStaffingRequirement;
use App\Filament\Resources\WorkforceStaffingRequirements\Schemas\WorkforceStaffingRequirementForm;
use App\Filament\Resources\WorkforceStaffingRequirements\Schemas\WorkforceStaffingRequirementInfolist;
use App\Filament\Resources\WorkforceStaffingRequirements\Tables\WorkforceStaffingRequirementsTable;
use App\Models\WorkforceStaffingRequirement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceStaffingRequirementResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_staffing_requirement';
    }

    protected static ?string $model = WorkforceStaffingRequirement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Staffing Requirements';

    public static function form(Schema $schema): Schema
    {
        return WorkforceStaffingRequirementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceStaffingRequirementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceStaffingRequirementsTable::configure($table);
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
            'index' => ListWorkforceStaffingRequirements::route('/'),
            'create' => CreateWorkforceStaffingRequirement::route('/create'),
            'view' => ViewWorkforceStaffingRequirement::route('/{record}'),
            'edit' => EditWorkforceStaffingRequirement::route('/{record}/edit'),
        ];
    }
}

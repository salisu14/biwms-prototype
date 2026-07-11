<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles;

use App\Filament\Resources\WorkforceRosterRoles\Pages\CreateWorkforceRosterRole;
use App\Filament\Resources\WorkforceRosterRoles\Pages\EditWorkforceRosterRole;
use App\Filament\Resources\WorkforceRosterRoles\Pages\ListWorkforceRosterRoles;
use App\Filament\Resources\WorkforceRosterRoles\Pages\ViewWorkforceRosterRole;
use App\Filament\Resources\WorkforceRosterRoles\Schemas\WorkforceRosterRoleForm;
use App\Filament\Resources\WorkforceRosterRoles\Schemas\WorkforceRosterRoleInfolist;
use App\Filament\Resources\WorkforceRosterRoles\Tables\WorkforceRosterRolesTable;
use App\Models\WorkforceRosterRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceRosterRoleResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_roster_role';
    }

    protected static ?string $model = WorkforceRosterRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Roster Roles';

    public static function form(Schema $schema): Schema
    {
        return WorkforceRosterRoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceRosterRoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceRosterRolesTable::configure($table);
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
            'index' => ListWorkforceRosterRoles::route('/'),
            'create' => CreateWorkforceRosterRole::route('/create'),
            'view' => ViewWorkforceRosterRole::route('/{record}'),
            'edit' => EditWorkforceRosterRole::route('/{record}/edit'),
        ];
    }
}

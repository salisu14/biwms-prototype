<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterPeriods;

use App\Filament\Resources\WorkforceRosterPeriods\Pages\CreateWorkforceRosterPeriod;
use App\Filament\Resources\WorkforceRosterPeriods\Pages\EditWorkforceRosterPeriod;
use App\Filament\Resources\WorkforceRosterPeriods\Pages\ListWorkforceRosterPeriods;
use App\Filament\Resources\WorkforceRosterPeriods\Pages\ViewWorkforceRosterPeriod;
use App\Filament\Resources\WorkforceRosterPeriods\Schemas\WorkforceRosterPeriodForm;
use App\Filament\Resources\WorkforceRosterPeriods\Schemas\WorkforceRosterPeriodInfolist;
use App\Filament\Resources\WorkforceRosterPeriods\Tables\WorkforceRosterPeriodsTable;
use App\Models\WorkforceRosterPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceRosterPeriodResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_roster_period';
    }

    protected static ?string $model = WorkforceRosterPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Roster Periods';

    public static function form(Schema $schema): Schema
    {
        return WorkforceRosterPeriodForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceRosterPeriodInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceRosterPeriodsTable::configure($table);
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
            'index' => ListWorkforceRosterPeriods::route('/'),
            'create' => CreateWorkforceRosterPeriod::route('/create'),
            'view' => ViewWorkforceRosterPeriod::route('/{record}'),
            'edit' => EditWorkforceRosterPeriod::route('/{record}/edit'),
        ];
    }
}

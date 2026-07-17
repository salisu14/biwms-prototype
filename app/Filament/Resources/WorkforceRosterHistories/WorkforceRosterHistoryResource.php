<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories;

use App\Filament\Resources\WorkforceRosterHistories\Pages\ListWorkforceRosterHistories;
use App\Filament\Resources\WorkforceRosterHistories\Pages\ViewWorkforceRosterHistory;
use App\Filament\Resources\WorkforceRosterHistories\Schemas\WorkforceRosterHistoryForm;
use App\Filament\Resources\WorkforceRosterHistories\Schemas\WorkforceRosterHistoryInfolist;
use App\Filament\Resources\WorkforceRosterHistories\Tables\WorkforceRosterHistoriesTable;
use App\Models\WorkforceRosterHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkforceRosterHistoryResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'hr';
    }

    public static function permissionResource(): string
    {
        return 'workforce_roster_history';
    }

    protected static ?string $model = WorkforceRosterHistory::class;

    protected static bool $isGloballySearchable = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Workforce Scheduling';

    protected static ?string $navigationLabel = 'Roster History';

    protected static ?int $navigationSort = 99;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return WorkforceRosterHistoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkforceRosterHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkforceRosterHistoriesTable::configure($table);
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
            'index' => ListWorkforceRosterHistories::route('/'),
            'view' => ViewWorkforceRosterHistory::route('/{record}'),
        ];
    }
}

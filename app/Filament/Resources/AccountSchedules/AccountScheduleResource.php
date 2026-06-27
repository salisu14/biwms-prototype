<?php

namespace App\Filament\Resources\AccountSchedules;

use App\Filament\Resources\AccountSchedules\Pages\CreateAccountSchedule;
use App\Filament\Resources\AccountSchedules\Pages\EditAccountSchedule;
use App\Filament\Resources\AccountSchedules\Pages\ListAccountSchedules;
use App\Filament\Resources\AccountSchedules\Schemas\AccountScheduleForm;
use App\Filament\Resources\AccountSchedules\Tables\AccountSchedulesTable;
use App\Models\AccountSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AccountScheduleResource extends Resource
{
    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function permissionResource(): string
    {
        return 'account_schedule';
    }

    protected static ?string $model = AccountSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return AccountScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountSchedulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountSchedules::route('/'),
            'create' => CreateAccountSchedule::route('/create'),
            'edit' => EditAccountSchedule::route('/{record}/edit'),
        ];
    }
}

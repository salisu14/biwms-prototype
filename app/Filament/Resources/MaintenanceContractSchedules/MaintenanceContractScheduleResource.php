<?php

namespace App\Filament\Resources\MaintenanceContractSchedules;

use App\Filament\Resources\MaintenanceContractSchedules\Pages\CreateMaintenanceContractSchedule;
use App\Filament\Resources\MaintenanceContractSchedules\Pages\EditMaintenanceContractSchedule;
use App\Filament\Resources\MaintenanceContractSchedules\Pages\ListMaintenanceContractSchedules;
use App\Filament\Resources\MaintenanceContractSchedules\Schemas\MaintenanceContractScheduleForm;
use App\Filament\Resources\MaintenanceContractSchedules\Tables\MaintenanceContractSchedulesTable;
use App\Models\MaintenanceContractSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MaintenanceContractScheduleResource extends Resource
{
    protected static ?string $model = MaintenanceContractSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Service Dispatches';

    protected static string|\UnitEnum|null $navigationGroup = 'Service Dispatch';

    protected static ?string $slug = 'service-dispatches';

    public static function form(Schema $schema): Schema
    {
        return MaintenanceContractScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceContractSchedulesTable::configure($table);
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
            'index' => ListMaintenanceContractSchedules::route('/'),
            'create' => CreateMaintenanceContractSchedule::route('/create'),
            'edit' => EditMaintenanceContractSchedule::route('/{record}/edit'),
        ];
    }
}

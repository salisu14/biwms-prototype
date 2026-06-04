<?php

namespace App\Filament\Resources\MaintenanceContractSchedules;

use App\Filament\Resources\MaintenanceContractSchedules\Pages\CreateMaintenanceContractSchedule;
use App\Filament\Resources\MaintenanceContractSchedules\Pages\EditMaintenanceContractSchedule;
use App\Filament\Resources\MaintenanceContractSchedules\Pages\ListMaintenanceContractSchedules;
use App\Filament\Resources\MaintenanceContractSchedules\Pages\ViewMaintenanceContractSchedule;
use App\Filament\Resources\MaintenanceContractSchedules\Schemas\MaintenanceContractScheduleForm;
use App\Filament\Resources\MaintenanceContractSchedules\Schemas\MaintenanceContractScheduleInfolist;
use App\Filament\Resources\MaintenanceContractSchedules\Tables\MaintenanceContractSchedulesTable;
use App\Models\MaintenanceContractSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceContractScheduleResource extends Resource
{
    protected static ?string $model = MaintenanceContractSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Service Dispatches';

    protected static string|\UnitEnum|null $navigationGroup = 'Service Contracts';

    protected static ?string $slug = 'service-dispatches';

    public static function form(Schema $schema): Schema
    {
        return MaintenanceContractScheduleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaintenanceContractScheduleInfolist::configure($schema);
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
            'view' => ViewMaintenanceContractSchedule::route('/{record}'),
            'edit' => EditMaintenanceContractSchedule::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof MaintenanceContractSchedule) {
            return static::getModelLabel();
        }

        $contract = $record->maintenanceContract?->contract_no ?? 'Unknown Contract';
        $asset = $record->fixedAsset?->fa_no ?? 'Unassigned Asset';
        $date = $record->next_service_date?->format('d/m/Y') ?? 'No date';

        return "{$contract} - {$asset} - {$date}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var MaintenanceContractSchedule $record */
        return [
            'Contract' => $record->maintenanceContract?->contract_no ?? '—',
            'Asset' => $record->fixedAsset?->fa_no ?? '—',
            'Frequency' => $record->frequency ? str($record->frequency)->replace('_', ' ')->title()->toString() : '—',
            'Next Service' => $record->next_service_date?->toDateString() ?? '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['maintenanceContract', 'fixedAsset']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'maintenanceContract.contract_no',
            'maintenanceContract.description',
            'fixedAsset.fa_no',
            'fixedAsset.description',
            'frequency',
            'service_description',
        ];
    }
}

<?php

namespace App\Filament\Resources\MaintenanceContracts;

use App\Filament\Resources\MaintenanceContracts\Pages\CreateMaintenanceContract;
use App\Filament\Resources\MaintenanceContracts\Pages\EditMaintenanceContract;
use App\Filament\Resources\MaintenanceContracts\Pages\ListMaintenanceContracts;
use App\Filament\Resources\MaintenanceContracts\Pages\ViewMaintenanceContract;
use App\Filament\Resources\MaintenanceContracts\Schemas\MaintenanceContractForm;
use App\Filament\Resources\MaintenanceContracts\Schemas\MaintenanceContractInfolist;
use App\Filament\Resources\MaintenanceContracts\Tables\MaintenanceContractsTable;
use App\Models\MaintenanceContract;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaintenanceContractResource extends Resource
{
    protected static ?string $model = MaintenanceContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Service Contracts';

    protected static string|\UnitEnum|null $navigationGroup = 'Service Contracts';

    protected static ?string $recordTitleAttribute = 'contract_no';

    protected static ?string $slug = 'service-contracts';

    public static function form(Schema $schema): Schema
    {
        return MaintenanceContractForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaintenanceContractInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceContractsTable::configure($table);
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
            'index' => ListMaintenanceContracts::route('/'),
            'create' => CreateMaintenanceContract::route('/create'),
            'view' => ViewMaintenanceContract::route('/{record}'),
            'edit' => EditMaintenanceContract::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof MaintenanceContract) {
            return static::getModelLabel();
        }

        return "{$record->contract_no} - {$record->description}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var MaintenanceContract $record */
        return [
            'Vendor' => $record->vendor?->vendor_name ?? '—',
            'Status' => $record->status?->value ?? '—',
            'Type' => $record->contract_type?->value ?? '—',
            'Value' => number_format((float) $record->contract_value, 2).' '.($record->currency_code ?: ''),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['vendor', 'responsibleEmployee'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'contract_no',
            'description',
            'external_reference',
            'vendor.vendor_name',
            'status',
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

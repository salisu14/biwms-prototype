<?php

namespace App\Filament\Resources\MaintenanceContractBillings;

use App\Filament\Resources\MaintenanceContractBillings\Pages\CreateMaintenanceContractBilling;
use App\Filament\Resources\MaintenanceContractBillings\Pages\EditMaintenanceContractBilling;
use App\Filament\Resources\MaintenanceContractBillings\Pages\ListMaintenanceContractBillings;
use App\Filament\Resources\MaintenanceContractBillings\Pages\ViewMaintenanceContractBilling;
use App\Filament\Resources\MaintenanceContractBillings\Schemas\MaintenanceContractBillingForm;
use App\Filament\Resources\MaintenanceContractBillings\Schemas\MaintenanceContractBillingInfolist;
use App\Filament\Resources\MaintenanceContractBillings\Tables\MaintenanceContractBillingsTable;
use App\Models\MaintenanceContractBilling;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MaintenanceContractBillingResource extends Resource
{
    protected static ?string $model = MaintenanceContractBilling::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceContractBillingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceContractBillingsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaintenanceContractBillingInfolist::configure($schema);
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
            'index' => ListMaintenanceContractBillings::route('/'),
            'create' => CreateMaintenanceContractBilling::route('/create'),
            'view' => ViewMaintenanceContractBilling::route('/{record}'),
            'edit' => EditMaintenanceContractBilling::route('/{record}/edit'),
        ];
    }

    public static function getRecordTitle(?Model $record): string
    {
        if (! $record instanceof MaintenanceContractBilling) {
            return static::getModelLabel();
        }

        $contract = $record->maintenanceContract
            ? "{$record->maintenanceContract->contract_no} - {$record->maintenanceContract->description}"
            : 'Unknown Contract';

        return "{$contract} - ".($record->billing_date?->format('d/m/Y') ?? 'Billing');
    }
}

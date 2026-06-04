<?php

namespace App\Filament\Resources\MaintenanceContractBillings;

use App\Filament\Resources\MaintenanceContractBillings\Pages\CreateMaintenanceContractBilling;
use App\Filament\Resources\MaintenanceContractBillings\Pages\EditMaintenanceContractBilling;
use App\Filament\Resources\MaintenanceContractBillings\Pages\ListMaintenanceContractBillings;
use App\Filament\Resources\MaintenanceContractBillings\Schemas\MaintenanceContractBillingForm;
use App\Filament\Resources\MaintenanceContractBillings\Tables\MaintenanceContractBillingsTable;
use App\Models\MaintenanceContractBilling;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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
            'edit' => EditMaintenanceContractBilling::route('/{record}/edit'),
        ];
    }
}

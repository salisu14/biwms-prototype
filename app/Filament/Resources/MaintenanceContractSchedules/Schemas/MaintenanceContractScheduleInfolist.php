<?php

namespace App\Filament\Resources\MaintenanceContractSchedules\Schemas;

use App\Filament\Resources\FixedAssets\FixedAssetResource;
use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Models\MaintenanceContractSchedule;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceContractScheduleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scope')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('maintenance_contract')
                            ->label('Maintenance Contract')
                            ->state(fn (MaintenanceContractSchedule $record): string => $record->maintenanceContract
                                ? "{$record->maintenanceContract->contract_no} - {$record->maintenanceContract->description}"
                                : '—')
                            ->url(fn (MaintenanceContractSchedule $record): ?string => $record->maintenanceContract
                                ? MaintenanceContractResource::getUrl('view', ['record' => $record->maintenanceContract])
                                : null),
                        TextEntry::make('fixed_asset')
                            ->label('Fixed Asset')
                            ->state(fn (MaintenanceContractSchedule $record): string => $record->fixedAsset
                                ? "{$record->fixedAsset->fa_no} - {$record->fixedAsset->description}"
                                : '—')
                            ->url(fn (MaintenanceContractSchedule $record): ?string => $record->fixedAsset
                                ? FixedAssetResource::getUrl('view', ['record' => $record->fixedAsset])
                                : null),
                    ]),

                Section::make('Schedule')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('frequency')
                            ->badge()
                            ->state(fn (MaintenanceContractSchedule $record): string => $record->frequency ? str($record->frequency)->replace('_', ' ')->title()->toString() : '—'),
                        TextEntry::make('interval_months')->label('Interval (Months)'),
                        TextEntry::make('is_active')->boolean()->label('Active'),
                        TextEntry::make('first_service_date')->date()->label('First Service Date'),
                        TextEntry::make('next_service_date')->date()->label('Next Service Date'),
                        TextEntry::make('last_service_date')->date()->label('Last Service Date'),
                        TextEntry::make('estimated_cost')
                            ->money(fn (MaintenanceContractSchedule $record) => $record->maintenanceContract?->currency_code ?? config('app.default_currency', 'NGN'))
                            ->label('Estimated Cost'),
                        TextEntry::make('service_description')
                            ->columnSpanFull()
                            ->label('Service Description'),
                    ]),

                Section::make('Audit')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}

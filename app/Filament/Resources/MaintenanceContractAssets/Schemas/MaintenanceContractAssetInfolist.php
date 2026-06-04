<?php

namespace App\Filament\Resources\MaintenanceContractAssets\Schemas;

use App\Filament\Resources\FixedAssets\FixedAssetResource;
use App\Filament\Resources\MaintenanceContracts\MaintenanceContractResource;
use App\Models\MaintenanceContractAsset;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceContractAssetInfolist
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
                            ->state(fn (MaintenanceContractAsset $record): string => $record->maintenanceContract
                                ? "{$record->maintenanceContract->contract_no} - {$record->maintenanceContract->description}"
                                : '—')
                            ->url(fn (MaintenanceContractAsset $record): ?string => $record->maintenanceContract
                                ? MaintenanceContractResource::getUrl('view', ['record' => $record->maintenanceContract])
                                : null),
                        TextEntry::make('fixed_asset')
                            ->label('Fixed Asset')
                            ->state(fn (MaintenanceContractAsset $record): string => $record->fixedAsset
                                ? "{$record->fixedAsset->fa_no} - {$record->fixedAsset->description}"
                                : '—')
                            ->url(fn (MaintenanceContractAsset $record): ?string => $record->fixedAsset
                                ? FixedAssetResource::getUrl('view', ['record' => $record->fixedAsset])
                                : null),
                    ]),

                Section::make('Coverage')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('covered_serial_no')->label('Covered Serial No.'),
                        TextEntry::make('asset_specific_limit')
                            ->state(fn (MaintenanceContractAsset $record): string => $record->asset_specific_limit === null
                                ? 'Unlimited'
                                : number_format((float) $record->asset_specific_limit, 2))
                            ->label('Coverage Limit'),
                        TextEntry::make('special_conditions')
                            ->columnSpanFull()
                            ->label('Special Conditions'),
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

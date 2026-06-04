<?php

namespace App\Filament\Resources\MaintenanceContractAssets\Schemas;

use App\Models\FixedAsset;
use App\Models\MaintenanceContract;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class MaintenanceContractAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Asset Coverage Details')
                    ->description('Link a fixed asset to this maintenance contract and define any specific coverage terms.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('maintenance_contract_id')
                                    ->label('Maintenance Contract')
                                    ->relationship('maintenanceContract', 'contract_no')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(
                                        fn (MaintenanceContract $record) => "{$record->contract_no} — {$record->description}"
                                    )
                                    ->getSearchResultsUsing(
                                        fn (string $search) => MaintenanceContract::where('contract_no', 'like', "%{$search}%")
                                            ->orWhere('description', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($contract) => [$contract->id => "{$contract->contract_no} — {$contract->description}"])
                                    ),

                                Select::make('fixed_asset_id')
                                    ->label('Fixed Asset')
                                    ->relationship('fixedAsset', 'fa_no') // Fallback attribute
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        // Auto-fill serial number from the asset if it exists
                                        if ($asset = FixedAsset::find($state)) {
                                            $set('covered_serial_no', $asset->serial_no ?? null);
                                        }
                                    })
                                    ->getOptionLabelFromRecordUsing(
                                        fn (FixedAsset $record) => "{$record->fa_no} — {$record->description}"
                                    )
                                    ->getSearchResultsUsing(
                                        fn (string $search) => FixedAsset::where('fa_no', 'like', "%{$search}%")
                                            ->orWhere('description', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($asset) => [$asset->id => "{$asset->fa_no} — {$asset->description}"])
                                    ),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('covered_serial_no')
                                    ->label('Covered Serial No.')
                                    ->maxLength(50)
                                    ->helperText('Auto-filled from asset, but can be overridden if tracking a specific component.'),

                                TextInput::make('asset_specific_limit')
                                    ->label('Coverage Limit')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Leave empty if coverage is unlimited for this asset.'),
                            ]),
                        Textarea::make('special_conditions')
                            ->label('Special Conditions / Exclusions')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('E.g., "Only covers compressor parts", "Excludes accidental damage".'),
                    ]),
            ]);
    }
}

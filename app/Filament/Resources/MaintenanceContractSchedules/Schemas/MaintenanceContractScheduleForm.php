<?php

namespace App\Filament\Resources\MaintenanceContractSchedules\Schemas;

use App\Models\FixedAsset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceContractScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dispatch Card')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('maintenance_contract_id')
                                ->label('Service Contract')
                                ->relationship('maintenanceContract', 'contract_no')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('fixed_asset_id')
                                ->relationship('fixedAsset', 'fa_no')
                                ->searchable()
                                ->preload()
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
                            Select::make('frequency')
                                ->options([
                                    'weekly' => 'Weekly',
                                    'monthly' => 'Monthly',
                                    'quarterly' => 'Quarterly',
                                    'semi_annual' => 'Semi Annual',
                                    'annual' => 'Annual',
                                    'custom' => 'Custom',
                                ])
                                ->default('monthly')
                                ->required(),
                            TextInput::make('interval_months')
                                ->numeric()
                                ->default(1)
                                ->required(),
                            DatePicker::make('first_service_date')->required(),
                            DatePicker::make('next_service_date')->required(),
                            DatePicker::make('last_service_date'),
                            TextInput::make('estimated_cost')->numeric(),
                            Toggle::make('is_active')->default(true),
                        ]),
                        Textarea::make('service_description')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

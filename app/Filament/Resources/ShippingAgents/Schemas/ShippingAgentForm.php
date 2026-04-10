<?php

namespace App\Filament\Resources\ShippingAgents\Schemas;

use App\Enums\ShippingAgentServiceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ShippingAgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Shipping Agent Details')
                    ->tabs([
                        Tab::make('General Information')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                    TextInput::make('name')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('search_name', $state)),
                                    TextInput::make('search_name')
                                        ->label('Search Name'),
                                ]),

                                Grid::make(2)->schema([
                                    Section::make('Status')
                                        ->schema([
                                            Toggle::make('is_active')
                                                ->label('Active')
                                                ->default(true)
                                                ->onColor('success'),
                                            Toggle::make('blocked')
                                                ->label('Blocked')
                                                ->onColor('danger'),
                                        ])->compact()->inlineLabel(),

                                    Section::make('Logistics Defaults')
                                        ->schema([
                                            Select::make('default_service_type')
                                                ->options(ShippingAgentServiceType::class)
                                                ->default(ShippingAgentServiceType::GROUND)
                                                ->required()
                                                ->native(false),
                                            TextInput::make('account_no')
                                                ->label('Carrier Account Number'),
                                        ])->compact(),
                                ]),
                            ]),

                        Tab::make('Contact & Address')
                            ->icon('heroicon-m-map-pin')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Address')
                                        ->schema([
                                            TextInput::make('address'),
                                            TextInput::make('address_2'),
                                            Grid::make(2)->schema([
                                                TextInput::make('city'),
                                                TextInput::make('post_code'),
                                            ]),
                                            TextInput::make('country_code')->label('Country Code (ISO)'),
                                        ]),
                                    Section::make('Communication')
                                        ->schema([
                                            TextInput::make('phone_no')->tel(),
                                            TextInput::make('email')->email(),
                                            TextInput::make('website')->url(),
                                        ]),
                                ]),
                            ]),

                        Tab::make('Financials & Charges')
                            ->icon('heroicon-m-banknotes')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Base Rates')
                                        ->schema([
                                            TextInput::make('base_charge')
                                                ->numeric()
                                                ->prefix('$')
                                                ->default(0),
                                            TextInput::make('handling_charge')
                                                ->numeric()
                                                ->prefix('$')
                                                ->default(0),
                                            TextInput::make('fuel_surcharge_percent')
                                                ->label('Fuel Surcharge %')
                                                ->numeric()
                                                ->suffix('%')
                                                ->default(0),
                                        ]),
                                    Section::make('Insurance')
                                        ->schema([
                                            Toggle::make('requires_insurance')
                                                ->label('Mandatory Insurance'),
                                            TextInput::make('default_insurance_amount')
                                                ->numeric()
                                                ->prefix('$'),
                                        ]),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('shortcut_dimension_1_code')->label('Global Dimension 1'),
                                    TextInput::make('shortcut_dimension_2_code')->label('Global Dimension 2'),
                                ]),
                            ]),

                        Tab::make('Integration')
                            ->icon('heroicon-m-cloud-arrow-up')
                            ->schema([
                                Section::make('API Connectivity')
                                    ->description('Configure external carrier API credentials.')
                                    ->schema([
                                        TextInput::make('api_endpoint')->url(),
                                        TextInput::make('api_key')
                                            ->password()
                                            ->revealable(),
                                        Textarea::make('notes')
                                            ->placeholder('Internal notes regarding integration behavior...')
                                            ->rows(3),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\ShipmentMethods\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Shipment Method Details')
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('code')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->extraInputAttributes(['style' => 'text-transform: uppercase']),
                                    TextInput::make('description')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('search_description', $state)),
                                    TextInput::make('transport_mode')
                                        ->label('Transport Mode')
                                        ->placeholder('e.g., AIR, OCEAN, ROAD'),
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

                                    Section::make('Search & Metadata')
                                        ->schema([
                                            TextInput::make('search_description')
                                                ->label('Search Description'),
                                        ])->compact(),
                                ]),
                            ]),

                        Tab::make('Incoterms & Responsibilities')
                            ->icon('heroicon-m-globe-americas')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Incoterm Configuration')
                                        ->schema([
                                            Toggle::make('is_incoterm')
                                                ->label('Is Standard Incoterm')
                                                ->reactive(),
                                            TextInput::make('incoterm_code')
                                                ->label('Incoterm Code')
                                                ->placeholder('e.g., FOB, DDP, EXW')
                                                ->visible(fn (callable $get) => $get('is_incoterm'))
                                                ->maxLength(3),
                                        ]),
                                    Section::make('Responsibility Matrix')
                                        ->description('Determine which party pays for specific costs.')
                                        ->schema([
                                            Toggle::make('seller_pays_freight')->label('Seller Pays Freight'),
                                            Toggle::make('seller_pays_insurance')->label('Seller Pays Insurance'),
                                            Toggle::make('seller_pays_duty')->label('Seller Pays Duty'),
                                        ]),
                                ]),
                            ]),

                        Tab::make('Logistics Defaults')
                            ->icon('heroicon-m-truck')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('default_shipping_agent_id')
                                        ->label('Default Shipping Agent')
                                        ->relationship('defaultShippingAgent', 'name')
                                        ->searchable()
                                        ->preload(),
                                    TextInput::make('default_service_code')
                                        ->label('Default Service Code'),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('shortcut_dimension_1_code')->label('Global Dimension 1'),
                                    TextInput::make('shortcut_dimension_2_code')->label('Global Dimension 2'),
                                ]),
                            ]),

                        Tab::make('Notes & Advanced')
                            ->icon('heroicon-m-adjustments-horizontal')
                            ->schema([
                                Textarea::make('notes')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TextInput::make('extended_fields')
                                    ->disabled()
                                    ->placeholder('System managed extended attributes'),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}

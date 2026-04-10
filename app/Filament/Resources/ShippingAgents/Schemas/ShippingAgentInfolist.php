<?php

namespace App\Filament\Resources\ShippingAgents\Schemas;

use App\Models\ShippingAgent;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingAgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TOP SECTION: Identity & Status
                Section::make('General Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Agent Code')
                                    ->weight('bold')
                                    ->size('lg'),

                                TextEntry::make('name')
                                    ->label('Name')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->columnSpan(2),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextEntry::make('search_name')
                                    ->label('Search Name')
                                    ->color('gray')
                                    ->size('sm'),

                                IconEntry::make('is_active')
                                    ->label('Active')
                                    ->boolean(),

                                IconEntry::make('blocked')
                                    ->label('Blocked')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-x-circle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success'),

                                TextEntry::make('default_service_type')
                                    ->label('Default Service')
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),

                // MIDDLE SECTION: Contact Info
                Section::make('Contact & Address')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Left: Address
                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('address')->label('Address'),
                                        TextEntry::make('address_2')->label('Address 2'),
                                        TextEntry::make('city')->label('City'),
                                        Grid::make(2)->schema([
                                            TextEntry::make('post_code')->label('Post Code'),
                                            TextEntry::make('country_code')->label('Country'),
                                        ]),
                                    ])->columnSpan(1),

                                // Right: Contact Details
                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('phone_no')
                                            ->label('Phone')
                                            ->icon('heroicon-m-phone')
                                            ->copyable(),

                                        TextEntry::make('email')
                                            ->label('Email')
                                            ->icon('heroicon-m-envelope')
                                            ->url(fn ($state) => $state ? "mailto:{$state}" : null),

                                        TextEntry::make('website')
                                            ->label('Website')
                                            ->icon('heroicon-m-globe-alt')
                                            ->url(fn ($state) => $state ? "https://{$state}" : null)
                                            ->openUrlInNewTab(),
                                    ])->columnSpan(1),
                            ]),
                    ])
                    ->collapsible(),

                // MIDDLE SECTION: API Integration
                Section::make('API Integration')
                    ->description('Credentials and endpoints for shipping rate calculation.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('account_no')
                                    ->label('Account No.')
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('api_endpoint')
                                    ->label('API Endpoint')
                                    ->url(fn ($state) => $state)
                                    ->openUrlInNewTab()
                                    ->limit(30)
                                    ->tooltip(fn ($state) => $state)
                                    ->placeholder('-'),

                                TextEntry::make('api_key')
                                    ->label('API Key')
//                                    ->password() // Masks the key for security
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // MIDDLE SECTION: Financials & Defaults
                Section::make('Pricing & Defaults')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                IconEntry::make('requires_insurance')
                                    ->label('Insurance Required')
                                    ->boolean()
                                    ->grow(false),

                                TextEntry::make('default_insurance_amount')
                                    ->label('Default Insurance')
                                    ->money('USD')
                                    ->placeholder('-'),

                                TextEntry::make('base_charge')
                                    ->label('Base Charge')
                                    ->money('USD')
                                    ->placeholder('-'),

                                TextEntry::make('fuel_surcharge_percent')
                                    ->label('Fuel Surcharge')
                                    ->suffix('%')
                                    ->numeric(2)
                                    ->placeholder('-'),

                                TextEntry::make('handling_charge')
                                    ->label('Handling Charge')
                                    ->money('USD')
                                    ->columnSpan(2), // Span extra width
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('shortcut_dimension_1_code')
                                    ->label('Global Dimension 1')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('-'),

                                TextEntry::make('shortcut_dimension_2_code')
                                    ->label('Global Dimension 2')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('-'),
                            ]),
                    ]),

                // BOTTOM SECTION: Audit & Notes
                Section::make('Details & Audit')
                    ->schema([
                        TextEntry::make('notes')
                            ->columnSpanFull()
                            ->markdown()
                            ->placeholder('No notes provided.'),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime()
                                    ->sortable(),

                                TextEntry::make('deleted_at')
                                    ->label('Deleted At')
                                    ->dateTime()
                                    ->color('danger')
                                    ->visible(fn (ShippingAgent $record): bool => $record->trashed()),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

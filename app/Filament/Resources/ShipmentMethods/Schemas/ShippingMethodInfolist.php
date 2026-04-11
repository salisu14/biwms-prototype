<?php

namespace App\Filament\Resources\ShipmentMethods\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShippingMethodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TOP SECTION: Identity
                Section::make('General Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Code')
                                    ->weight('bold')
                                    ->size('lg'),

                                TextEntry::make('description')
                                    ->label('Description')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->columnSpan(2),

                                TextEntry::make('search_description')
                                    ->label('Search Description')
                                    ->color('gray')
                                    ->size('sm')
                                    ->columnSpanFull(),

                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('transport_mode')
                                            ->label('Transport Mode')
                                            ->badge()
                                            ->color('primary'),

                                        IconEntry::make('is_incoterm')
                                            ->label('Is Incoterm')
                                            ->boolean()
                                            ->trueIcon('heroicon-o-globe-alt')
                                            ->falseIcon('heroicon-o-x-circle'),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->formatStateUsing(fn ($record) => $record->blocked ? 'Blocked' : ($record->is_active ? 'Active' : 'Inactive'))
                                            ->badge()
                                            ->color(fn ($state) => match ($state) {
                                                'Blocked' => 'danger',
                                                'Active' => 'success',
                                                default => 'gray',
                                            }),
                                    ]),
                            ]),
                    ]),

                // MIDDLE SECTION: Incoterms & Responsibility Matrix
                Section::make('Incoterms & Responsibilities')
                    ->description('Defines who is responsible for the costs and risks at each stage of the shipment.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Left: Incoterm Code
                                TextEntry::make('incoterm_code')
                                    ->label('Incoterm Code')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                                    ->size('lg')
                                    ->formatStateUsing(fn ($state) => $state ?: 'N/A (Standard Shipment)'),

                                // Right: Visual "Who Pays?" Matrix
                                Grid::make(3)
                                    ->schema([
                                        // Freight
                                        IconEntry::make('seller_pays_freight')
                                            ->label('Freight')
                                            ->boolean()
                                            ->trueIcon('heroicon-o-truck')
                                            ->falseIcon('heroicon-o-user')
                                            ->trueColor('primary')
                                            ->falseColor('gray')
//                                            ->trueLabel('Seller Pays')
                                            ->falseLabel('Buyer Pays'),

                                        // Insurance
                                        IconEntry::make('seller_pays_insurance')
                                            ->label('Insurance')
                                            ->boolean()
                                            ->trueIcon('heroicon-o-shield-check')
                                            ->falseIcon('heroicon-o-user')
                                            ->trueColor('primary')
                                            ->falseColor('gray')
//                                            ->trueLabel('Seller Pays')
                                            ->falseLabel('Buyer Pays'),

                                        // Duty
                                        IconEntry::make('seller_pays_duty')
                                            ->label('Duty/Tax')
                                            ->boolean()
                                            ->trueIcon('heroicono-document-currency-dollar') // Note: might need standard icon if custom not available
                                            ->trueIcon('heroicon-o-currency-dollar')
                                            ->falseIcon('heroicon-o-user')
                                            ->trueColor('primary')
                                            ->falseColor('gray')
                                            ->trueLabel('Seller Pays')
                                            ->falseLabel('Buyer Pays'),
                                    ])
                                    ->columnSpan(1), // Take right column
                            ]),
                    ]),

                // BOTTOM LEFT: Defaults
                Section::make('Defaults & Dimensions')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('defaultShippingAgent.name')
                                    ->label('Default Agent')
                                    ->placeholder('-'),

                                TextEntry::make('default_service_code')
                                    ->label('Default Service Code')
                                    ->placeholder('-'),
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
                    ])
                    ->collapsible()
                    ->collapsed(),

                // BOTTOM RIGHT: Audit & Notes
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
                                    ->dateTime(),

                                TextEntry::make('deleted_at')
                                    ->label('Deleted At')
                                    ->dateTime()
                                    ->color('danger')
                                    ->visible(fn ($record) => $record->trashed()),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}

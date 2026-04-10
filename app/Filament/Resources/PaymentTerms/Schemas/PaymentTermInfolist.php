<?php

namespace App\Filament\Resources\PaymentTerms\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentTermInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TOP SECTION: Identity & Summary
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

                                // Display the "2/10 Net 30" style string
                                TextEntry::make('formatted_description')
                                    ->label('Calculated Term')
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn ($record) => $record->getFormattedDescription())
                                    ->columnSpanFull(),

                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('calculation_type')
                                            ->label('Due Date Calculation')
                                            ->badge()
                                            ->color('primary'),

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
                                    ]),
                            ]),
                    ]),

                // MIDDLE SECTION: Due Date Configuration
                Section::make('Due Date Configuration')
                    ->description('Rules used to determine when payment is expected.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('due_date_net_days')
                                    ->label('Net Days')
                                    ->formatStateUsing(fn ($state) => $state ? "{$state} Days" : '-')
                                    ->visible(fn ($record) => in_array($record->calculation_type->value, ['net', 'end_of_month', 'end_of_next_month'])),

                                TextEntry::make('due_date_day_of_month')
                                    ->label('Day of Month')
                                    ->formatStateUsing(fn ($state) => $state ? "Day {$state}" : '-')
                                    ->visible(fn ($record) => in_array($record->calculation_type->value, ['due_date', 'due_day'])),

                                TextEntry::make('due_date_months_ahead')
                                    ->label('Months Ahead')
                                    ->formatStateUsing(fn ($state) => $state ? "+{$state} Months" : '-')
                                    ->visible(fn ($record) => $record->calculation_type->value === 'due_day'),
                            ]),
                    ])
                    ->collapsible(),

                // MIDDLE SECTION: Discounts
                Section::make('Payment Discount')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                IconEntry::make('discount_allowed')
                                    ->label('Discount Allowed')
                                    ->boolean()
                                    ->grow(false),

                                TextEntry::make('discount_percent')
                                    ->label('Discount %')
                                    ->suffix('%')
                                    ->numeric(2)
                                    ->visible(fn ($record) => $record->discount_allowed)
                                    ->weight('bold'),

                                TextEntry::make('discount_calculation_type')
                                    ->label('Discount Date Calc')
                                    ->badge()
                                    ->color('gray')
                                    ->visible(fn ($record) => $record->discount_allowed),

                                TextEntry::make('discount_net_days')
                                    ->label('Discount Days')
                                    ->formatStateUsing(fn ($state) => $state ? "{$state} Days" : '-')
                                    ->visible(fn ($record) => $record->discount_allowed),
                            ]),
                    ])
                    ->collapsible(),

                // MIDDLE SECTION: Tolerance & Penalties
                Section::make('Tolerance & Penalties')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        IconEntry::make('payment_tolerance_enabled')
                                            ->label('Payment Tolerance')
                                            ->boolean(),

                                        TextEntry::make('payment_tolerance_percent')
                                            ->label('Tolerance Percent')
                                            ->suffix('%')
                                            ->visible(fn ($record) => $record->payment_tolerance_enabled),

                                        TextEntry::make('max_payment_tolerance_amount')
                                            ->label('Max Tolerance Amount')
                                            ->money('USD') // Assuming USD, or use currency code from a relation if available
                                            ->visible(fn ($record) => $record->payment_tolerance_enabled),
                                    ])
                                    ->columnSpan(1),

                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('late_payment_penalty_percent')
                                            ->label('Late Penalty %')
                                            ->suffix('%')
                                            ->numeric(2)
                                            ->placeholder('None'),

                                        TextEntry::make('late_payment_grace_days')
                                            ->label('Grace Period (Days)')
                                            ->numeric()
                                            ->placeholder('None'),
                                    ])
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // BOTTOM SECTION: Financials & Audit
                Section::make('Financial Integration & Audit')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('discountAccount.name')
                                    ->label('Discount Account')
                                    ->placeholder('-'),

                                TextEntry::make('toleranceAccount.name')
                                    ->label('Tolerance Account')
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

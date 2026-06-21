<?php

declare(strict_types=1);

namespace App\Filament\Resources\FAPostingGroups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Top: General Info & Settings
                Grid::make(2)
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('code')
                                        ->label('Code')
                                        ->weight('bold')
                                        ->size('lg')
                                        ->columnSpan(1),

                                    IconEntry::make('is_active')
                                        ->label('Active')
                                        ->boolean()
                                        ->columnSpan(1),

                                    TextEntry::make('description')
                                        ->label('Description')
                                        ->columnSpanFull()
                                        ->placeholder('No description provided.'),
                                ]),
                            ])->columnSpan(1),

                        Section::make('Depreciation Defaults')
                            ->description('Default depreciation settings for this group.')
                            ->schema([
                                IconEntry::make('auto_depreciate_acquisition_year')
                                    ->label('Depreciate in Acquisition Year')
                                    ->boolean(),

                                TextEntry::make('depreciation_calculation')
                                    ->label('Calculation Method')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'full_year' => 'Full Year',
                                        'pro_rata' => 'Pro Rata',
                                        'half_year' => 'Half Year',
                                        default => $state,
                                    }),

                                TextEntry::make('depreciation_start')
                                    ->label('Start Rule')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'acquisition' => 'Acquisition Date',
                                        'first_day_next_month' => 'First Day Next Month',
                                        default => $state,
                                    }),
                            ])->columnSpan(1),
                    ]),

                // Middle: Acquisition & Depreciation
                Grid::make(2)
                    ->schema([
                        Section::make('Acquisition')
                            ->description('Accounts for asset purchase and capitalization.')
                            ->schema([
                                TextEntry::make('acquisitionCostAccount.name')
                                    ->label('Acquisition Cost Account')
                                    ->icon('heroicon-m-currency-dollar')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('acquisitionCostAccountLcy.name')
                                    ->label('Acquisition Cost Account (LCY)')
                                    ->icon('heroicon-m-banknotes')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),

                        Section::make('Depreciation')
                            ->description('Accounts for calculating depreciation expenses.')
                            ->schema([
                                TextEntry::make('depreciationExpenseAccount.name')
                                    ->label('Depreciation Expense Account')
                                    ->icon('heroicon-m-chart-bar')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('accumulatedDepreciationAccount.name')
                                    ->label('Accumulated Depreciation Account')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),
                    ]),

                // Middle: Disposal & Maintenance
                Grid::make(2)
                    ->schema([
                        Section::make('Disposal')
                            ->description('Accounts used when selling or scrapping assets.')
                            ->schema([
                                TextEntry::make('disposalProceedsAccount.name')
                                    ->label('Disposal Proceeds Account')
                                    ->icon('heroicon-m-banknotes')
                                    ->placeholder('Not Configured'),

                                Grid::make(2)->schema([
                                    TextEntry::make('disposalGainAccount.name')
                                        ->label('Gain Account')
                                        ->color('success')
                                        ->placeholder('-'),

                                    TextEntry::make('disposalLossAccount.name')
                                        ->label('Loss Account')
                                        ->color('danger')
                                        ->placeholder('-'),
                                ]),
                            ])->columnSpan(1),

                        Section::make('Maintenance & Capitalization')
                            ->schema([
                                TextEntry::make('maintenanceExpenseAccount.name')
                                    ->label('Maintenance Expense Account')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('capitalizationAccount.name')
                                    ->label('Capitalization Account (CWIP)')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),
                    ]),

                // Bottom: Revaluation & Tax
                Grid::make(2)
                    ->schema([
                        Section::make('Revaluation')
                            ->schema([
                                TextEntry::make('revaluationAccount.name')
                                    ->label('Revaluation Account')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('reversalOfRevaluation.name')
                                    ->label('Reversal of Revaluation')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),

                        Section::make('Tax & System')
                            ->schema([
                                TextEntry::make('taxDepreciationAccount.name')
                                    ->label('Tax Depreciation Account')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('deferredTaxAccount.name')
                                    ->label('Deferred Tax Account')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ])->columnSpan(1),
                    ]),
            ]);
    }
}

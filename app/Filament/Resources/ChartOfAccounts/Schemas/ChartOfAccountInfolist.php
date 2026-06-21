<?php

namespace App\Filament\Resources\ChartOfAccounts\Schemas;

use App\Enums\IncomeBalanceType;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChartOfAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Changed to Grid::make(3) to make it wider and more horizontal
                Grid::make(3)
                    ->schema([
                        // COLUMN 1: Identity & Hierarchy
                        Group::make([
                            Section::make('General Information')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('account_number')
                                            ->label('Account No.')
                                            ->weight('bold')
                                            ->copyable(),

                                        TextEntry::make('name')
                                            ->weight('bold')
                                            ->columnSpanFull(),

                                        TextEntry::make('account_category')
                                            ->label('Category')
                                            ->badge()
                                            ->color('gray'),

                                        TextEntry::make('structural_type')
                                            ->label('Type')
                                            ->badge()
                                            ->color('info'),

                                        TextEntry::make('income_balance')
                                            ->label('Financial Statement')
                                            ->badge()
                                            ->color(fn($state) => $state === IncomeBalanceType::BALANCE_SHEET ? 'gray' : 'success'),

                                        TextEntry::make('parentAccount.name')
                                            ->label('Parent Account')
                                            ->placeholder('Root Level'),
                                    ]),
                                ]),
                        ]),

                        // COLUMN 2: Posting Logic & Controls
                        Group::make([
                            Section::make('Posting Configuration')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('genBusPostingGroup.code')
                                            ->label('Gen. Bus. Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),

                                        TextEntry::make('genProdPostingGroup.code')
                                            ->label('Gen. Prod. Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),

                                        TextEntry::make('vatBusPostingGroup.code')
                                            ->label('VAT Bus. Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),

                                        TextEntry::make('vatProdPostingGroup.code')
                                            ->label('VAT Prod. Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),
                                    ]),
                                ]),

                            Section::make('Posting Controls')
                                ->schema([
                                    Grid::make(2)->schema([
                                        IconEntry::make('direct_posting')
                                            ->label('Direct Posting')
                                            ->boolean(),

                                        IconEntry::make('blocked')
                                            ->label('Blocked')
                                            ->boolean()
                                            ->trueColor('danger')
                                            ->trueIcon('heroicon-o-lock-closed'),
                                    ]),

                                    Grid::make(2)->schema([
                                        TextEntry::make('blocked_from')
                                            ->label('From')
                                            ->date()
                                            ->placeholder('-'),

                                        TextEntry::make('blocked_to')
                                            ->label('To')
                                            ->date()
                                            ->placeholder('-'),
                                    ]),
                                ]),
                        ]),

                        // COLUMN 3: Reporting & Financial Status
                        Group::make([
                            Section::make('Reporting & Layout')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('totaling')
                                            ->placeholder('-'),

                                        TextEntry::make('indentation')
                                            ->formatStateUsing(fn ($state) => str_repeat('• ', (int)$state))
                                            ->placeholder('None'),

                                        TextEntry::make('no_of_blank_lines')
                                            ->label('Blank Lines'),

                                        IconEntry::make('new_page')
                                            ->label('New Page')
                                            ->boolean(),
                                    ]),

                                    Grid::make(4)->schema([
                                        IconEntry::make('bold')->boolean(),
                                        IconEntry::make('italic')->boolean(),
                                        IconEntry::make('underline')->boolean(),
                                        IconEntry::make('show_opposite_sign')->label('Opp. Sign')->boolean(),
                                    ]),
                                ]),

                            Section::make('Financial Status')
                                ->schema([
                                    TextEntry::make('balance')
                                        ->label('Current Balance')
                                        ->money('NGN')
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color(fn($state) => $state < 0 ? 'danger' : 'success'),

                                    TextEntry::make('balance_at_date')
                                        ->label('Balance at Date')
                                        ->money('NGN')
                                        ->color('gray'),
                                ]),

                            Section::make('System Details')
                                ->collapsed()
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime()
                                            ->size('sm'),

                                        TextEntry::make('updated_at')
                                            ->label('Updated')
                                            ->dateTime()
                                            ->size('sm'),
                                    ]),
                                ]),
                        ]),
                    ]),
            ]);
    }
}

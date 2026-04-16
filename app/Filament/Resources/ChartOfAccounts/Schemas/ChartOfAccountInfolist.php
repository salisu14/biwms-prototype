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
                // Replaced Split::make with Grid::make(2) for a 2-column layout
                Grid::make(2)
                    ->schema([
                        // LEFT COLUMN: General Info, Posting Groups, Formatting
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
                                            ->badge(),

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

                            Section::make('Posting Configuration')
                                ->schema([
                                    Grid::make(2)->schema([
                                        // Using direct fields as per your model $fillable
                                        TextEntry::make('gen_bus_posting_group')
                                            ->label('Gen. Bus. Posting Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),

                                        TextEntry::make('gen_prod_posting_group')
                                            ->label('Gen. Prod. Posting Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),

                                        TextEntry::make('vat_bus_posting_group')
                                            ->label('VAT Bus. Posting Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),

                                        TextEntry::make('vat_prod_posting_group')
                                            ->label('VAT Prod. Posting Group')
                                            ->badge()
                                            ->color('warning')
                                            ->placeholder('-'),
                                    ]),
                                ]),

                            Section::make('Reporting & Layout')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextEntry::make('totaling')
                                            ->label('Totaling')
                                            ->placeholder('-'),

                                        TextEntry::make('indentation')
                                            ->label('Indentation')
                                            ->formatStateUsing(fn ($state) => str_repeat('• ', $state))
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
                                        IconEntry::make('show_opposite_sign')->boolean(),
                                    ])->columns(4),
                                ]),
                        ]), // End Left Group

                        // RIGHT COLUMN: Financials, Controls, Audit
                        Group::make([
                            Section::make('Financial Status')
                                ->schema([
                                    TextEntry::make('balance')
                                        ->label('Current Balance')
                                        ->money() // Defaults to currency config
                                        ->size('lg')
                                        ->weight('bold')
                                        ->color(fn($state) => $state < 0 ? 'danger' : 'success'),

                                    TextEntry::make('balance_at_date')
                                        ->label('Balance at Date')
                                        ->money()
                                        ->color('gray'),
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
                                            ->label('Blocked From')
                                            ->date()
                                            ->placeholder('-'),

                                        TextEntry::make('blocked_to')
                                            ->label('Blocked To')
                                            ->date()
                                            ->placeholder('-'),
                                    ]),
                                ]),

                            Section::make('System Details')
                                ->schema([
                                    TextEntry::make('created_at')
                                        ->label('Created At')
                                        ->dateTime()
                                        ->size('sm'),

                                    TextEntry::make('updated_at')
                                        ->label('Last Updated')
                                        ->dateTime()
                                        ->size('sm'),
                                ]),
                        ]), // End Right Group
                    ]),
            ]);
    }
}

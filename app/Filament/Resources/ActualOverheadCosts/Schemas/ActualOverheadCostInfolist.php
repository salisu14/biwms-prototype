<?php

namespace App\Filament\Resources\ActualOverheadCosts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActualOverheadCostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resource Assignment')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('workCenter.name')
                            ->label('Work Center')
                            ->weight('bold'),
                        TextEntry::make('machineCenter.name')
                            ->label('Machine Center')
                            ->placeholder('Factory Level'),
                        TextEntry::make('location.name')
                            ->label('Location'),
                    ]),

                Section::make('Period Information')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('period')
                            ->label('Fiscal Month')
                            ->date('M Y'),
                        TextEntry::make('fiscal_year')
                            ->label('Year'),
                        TextEntry::make('period_no')
                            ->label('Period #'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'unallocated' => 'danger',
                                'partial' => 'warning',
                                'fully_allocated' => 'success',
                                'variance_posted' => 'info',
                                default => 'gray',
                            }),
                    ]),

                Section::make('Cost & Allocation Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('amount')
                            ->label('Actual Total')
                            ->money()
                            ->weight('bold'),

                        TextEntry::make('allocated_amount')
                            ->label('Already Allocated')
                            ->money(),

                        TextEntry::make('remaining_amount')
                            ->label('Remaining Balance')
                            ->money()
                            ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                        TextEntry::make('cost_type')
                            ->label('Cost Category')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('cost_type_code')
                            ->label('Cost Code'),
                    ]),

                Section::make('Financial Integration')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('glAccount.account_number')
                            ->label('G/L Account')
                            ->formatStateUsing(fn ($state, $record) => "{$state} – {$record->glAccount?->name}")
                            ->icon('heroicon-m-building-library'),

                        TextEntry::make('document_no')
                            ->label('Reference Doc')
                            ->placeholder('No reference'),

                        TextEntry::make('varianceJournalBatch.name')
                            ->label('Variance Journal')
                            ->placeholder('N/A'),

                        TextEntry::make('variance_posted_at')
                            ->label('Variance Posted Date')
                            ->dateTime()
                            ->placeholder('Pending'),
                    ]),

                Section::make('Descriptions & Audit')
                    ->schema([
                        TextEntry::make('description')
                            ->markdown(),
                        TextEntry::make('notes')
                            ->placeholder('No notes provided'),

                        Grid::make(3)->schema([
                            TextEntry::make('creator.name')->label('Created By'),
                            TextEntry::make('created_at')->label('Created At')->dateTime(),
                            TextEntry::make('updated_at')->label('Last Updated')->dateTime(),
                        ]),
                    ]),
            ]);
    }
}

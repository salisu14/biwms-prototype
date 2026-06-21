<?php

namespace App\Filament\Resources\WorkCenters\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkCenterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->weight('bold'),

                        TextEntry::make('name')
                            ->label('Name'),

                        TextEntry::make('group.name')
                            ->label('Group')
                            ->badge()
                            ->color('gray'),

                        IconEntry::make('subcontractor_id')
                            ->label('Subcontractor')
                            ->boolean()
                            ->trueIcon('heroicon-o-globe-alt')
                            ->falseIcon('heroicon-o-building-office')
                            ->trueColor('warning')
                            ->falseColor('success')
                            ->tooltip(fn ($record): string => $record->subcontractor ? 'Outsourced' : 'Internal'),
                    ]),

                Section::make('Capacity & Efficiency')
                    ->columns(2)
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('unit_of_measure_code')
                                ->label('Time Unit'),

                            TextEntry::make('capacity')
                                ->label('Capacity (per period)')
                                ->numeric(),

                            TextEntry::make('efficiency')
                                ->label('Current Efficiency')
                                ->suffix('%')
                                ->color(fn ($record): string => $record->efficiency >= 90
                                    ? 'success'
                                    : ($record->efficiency >= 75 ? 'warning' : 'danger')
                                ),
                        ]),

                        TextEntry::make('effective_capacity')
                            ->label('Effective Capacity')
                            ->state(function ($record): string {
                                $cap = $record->capacity ?? 0;
                                $eff = $record->efficiency ?? 0;

                                $effective = $cap * ($eff / 100);

                                return number_format($effective, 4);
                            })
                            ->columnSpanFull()
                            ->inlineLabel(),
                    ]),

                Section::make('Costing')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('direct_unit_cost')
                            ->label('Direct Unit Cost')
                            ->money('NGN'),

                        TextEntry::make('indirect_cost_percent')
                            ->label('Indirect Cost %')
                            ->suffix('%'),

                        TextEntry::make('overhead_rate')
                            ->label('Overhead Rate')
                            ->money('NGN'),
                    ])
                    ->collapsible(),

                Section::make('Scheduling')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('queue_time')
                                ->label('Queue Time')
                                ->numeric()
                                ->suffix(fn ($record): string => $record->unit_of_measure_code ?? ''),

                            TextEntry::make('location_code')
                                ->label('Location Code'),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Financial Setup')
                    ->description('General Ledger integration for WIP and capacity posting.')
                    ->schema([
                        TextEntry::make('glAccount.account_number')
                            ->label('G/L Account')
                            ->formatStateUsing(function ($state, $record) {
                                $name = $record->glAccount?->name;
                                return $name ? "{$state} — {$name}" : $state;
                            })
                            ->icon('heroicon-m-building-library')
                            ->placeholder('No account mapped'),
                    ])
                    ->collapsible(),
            ]);
    }
}

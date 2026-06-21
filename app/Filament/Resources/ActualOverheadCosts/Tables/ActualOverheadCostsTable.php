<?php

namespace App\Filament\Resources\ActualOverheadCosts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActualOverheadCostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('period')
                    ->label('Period')
                    ->date('M Y')
                    ->sortable(),

                TextColumn::make('cost_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Actual')
                    ->money('NGN')
                    ->alignment('right')
                    ->summarize(Sum::make()->money('NGN'))
                    ->sortable(),

                TextColumn::make('allocated_amount')
                    ->label('Allocated')
                    ->money('NGN')
                    ->alignment('right')
                    ->summarize(Sum::make()->money('NGN'))
                    ->sortable(),

                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('NGN')
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unallocated' => 'danger',
                        'partial' => 'warning',
                        'fully_allocated' => 'success',
                        'variance_posted' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('glAccount.account_number')
                    ->label('G/L Account')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('document_no')
                    ->label('Doc No.')
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('period', 'desc')
            ->filters([
                SelectFilter::make('work_center_id')
                    ->label('Work Center')
                    ->relationship('workCenter', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name'),

                SelectFilter::make('status')
                    ->options([
                        'unallocated' => 'Unallocated',
                        'partial' => 'Partially Allocated',
                        'fully_allocated' => 'Fully Allocated',
                        'variance_posted' => 'Variance Posted',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

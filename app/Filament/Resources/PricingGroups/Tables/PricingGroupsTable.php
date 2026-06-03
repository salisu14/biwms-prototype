<?php

namespace App\Filament\Resources\PricingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PricingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('status')
                    ->label('Status')
                    ->state(function ($record) {
                        if ($record->blocked) return 'Blocked';
                        if ($record->start_date && $record->start_date > now()) return 'Scheduled';
                        if ($record->end_date && $record->end_date < now()) return 'Expired';
                        return 'Active';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Scheduled' => 'info',
                        'Blocked' => 'danger',
                        'Expired' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Active' => 'heroicon-o-check-circle',
                        'Scheduled' => 'heroicon-o-clock',
                        'Blocked' => 'heroicon-o-x-circle',
                        'Expired' => 'heroicon-o-exclamation-triangle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(false),

                TextColumn::make('pricing_strategy')
                    ->label('Strategy')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', $state))
                    ->searchable(),

                TextColumn::make('default_discount_percent')
                    ->label('Disc. %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('default_markup_percent')
                    ->label('Markup %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('customers_count')
                    ->label('Customers')
                    ->counts('customers')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('currency_code')
                    ->label('Currency')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('generalBusinessPostingGroup.code') // Adjust to 'name' if needed
                ->label('Posting Group')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                IconColumn::make('enforce_minimum_margin')
                    ->label('Min Margin')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Currently Active')
                    ->placeholder('All Groups')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive/Blocked')
                    ->queries(
                        true: fn ($query) => $query->active(), // Leverages model scope
                        false: fn ($query) => $query->where('blocked', true)
                            ->orWhere(function ($q) {
                                $q->whereNotNull('end_date')->where('end_date', '<', now());
                            }),
                    ),
                SelectFilter::make('pricing_strategy')
                    ->options([
                        'STANDARD' => 'Standard',
                        'COST_PLUS' => 'Cost Plus',
                        'DISCOUNT' => 'Discount',
                        'MARGIN' => 'Margin',
                    ]),
                TernaryFilter::make('blocked')
                    ->label('Blocked Status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }
}

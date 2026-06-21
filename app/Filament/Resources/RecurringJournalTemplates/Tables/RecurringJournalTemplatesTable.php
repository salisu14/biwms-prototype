<?php

namespace App\Filament\Resources\RecurringJournalTemplates\Tables;

use App\Enums\RecurringMethod;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RecurringJournalTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('recurring_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn (RecurringMethod $state) => match ($state) {
                        RecurringMethod::FIXED => 'info',
                        RecurringMethod::VARIABLE => 'warning',
                        RecurringMethod::BALANCE => 'gray',
                        RecurringMethod::REVERSING_FIXED,
                        RecurringMethod::REVERSING_VARIABLE,
                        RecurringMethod::REVERSING_BALANCE => 'danger',
                    }),

                TextColumn::make('recurring_frequency')
                    ->label('Frequency')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('recurring_interval')
                    ->label('Every N')
                    ->suffix('×')
                    ->alignment('right'),

                TextColumn::make('start_date')
                    ->label('Start')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End')
                    ->date()
                    ->placeholder('Open-ended')
                    ->sortable(),

                TextColumn::make('last_posting_date')
                    ->label('Last Posted')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_posting_date')
                    ->label('Next Due')
                    ->dateTime()
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('auto_post')
                    ->label('Auto Post')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('auto_reverse')
                    ->label('Auto Reverse')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('recurring_method')
                    ->label('Method')
                    ->options(RecurringMethod::class)
                    ->native(false),

                SelectFilter::make('recurring_frequency')
                    ->label('Frequency')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                        'yearly' => 'Yearly',
                    ])
                    ->native(false),

                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('auto_post')->label('Auto Post'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

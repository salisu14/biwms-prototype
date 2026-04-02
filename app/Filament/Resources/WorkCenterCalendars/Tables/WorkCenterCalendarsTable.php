<?php

namespace App\Filament\Resources\WorkCenterCalendars\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkCenterCalendarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workCenter.name')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_working_day')
                    ->boolean(),
                TextColumn::make('start_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('end_time')
                    ->time()
                    ->sortable(),
                TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('efficiency')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('absence_code')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('work_center_id')
                    ->label('Work Center')
                    ->relationship('workCenter', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_working_day')
                    ->label('Working Day Status')
                    ->placeholder('All Days')
                    ->trueLabel('Working Days Only')
                    ->falseLabel('Non-Working Days / Holidays'),

                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
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

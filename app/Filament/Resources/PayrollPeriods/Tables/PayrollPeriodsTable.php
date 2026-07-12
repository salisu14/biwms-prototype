<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollPeriods\Tables;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Services\PayrollPeriodService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('start_date')
                    ->label('Starts')
                    ->date()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('end_date')
                    ->label('Ends')
                    ->date()
                    ->sortable(),

                TextColumn::make('payment_date')
                    ->label('Pay Date')
                    ->date()
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                ToggleColumn::make('is_current')
                    ->label('Current'),

                TextColumn::make('documents_count')
                    ->label('Docs')
                    ->counts('documents')
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(PayrollPeriodStatus::class),

                Filter::make('is_current')
                    ->label('Current Period Only')
                    ->query(fn (Builder $query) => $query->where('is_current', true)),

                Filter::make('period_range')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('start_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('end_date', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                EditAction::make(),

                Action::make('close')
                    ->label('Close Period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Close Payroll Period?')
                    ->modalDescription('This will freeze documents and update YTD balances. This action is irreversible.')
                    ->visible(fn (PayrollPeriod $record) => $record->status !== PayrollPeriodStatus::CLOSED)
                    ->action(function (PayrollPeriod $record) {
                        try {
                            app(PayrollPeriodService::class)->close($record);
                            Notification::make()
                                ->success()
                                ->title('Period Closed Successfully')
                                ->body("Year-to-Date balances for the period ending {$record->end_date->format('M d, Y')} have been updated.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Action Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\PettyCashFunds\Tables;

use App\Models\PettyCashFund;
use App\Services\NumberSeriesService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PettyCashFundsTable
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
                    ->label('Fund Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('custodian.name')
                    ->label('Custodian')
                    ->searchable(),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('imprest_amount')
                    ->label('Imprest')
                    ->money(fn ($record) => $record->currency ?? 'NGN')
                    ->sortable(),

                TextColumn::make('current_balance')
                    ->label('Balance')
                    ->money(fn ($record) => $record->currency ?? 'NGN')
                    ->sortable()
                    ->color('success'),

                TextColumn::make('utilization')
                    ->label('Used')
                    ->state(function ($record) {
                        if ($record->imprest_amount <= 0) return '0%';
                        $used = $record->imprest_amount - $record->current_balance;
                        return round(($used / $record->imprest_amount) * 100, 0) . '%';
                    })
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) str_replace('%', '', $state) >= 80 => 'danger',
                        (int) str_replace('%', '', $state) >= 50 => 'warning',
                        default => 'info',
                    }),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->trueLabel('Active Funds')
                    ->falseLabel('Inactive Funds'),

                SelectFilter::make('custodian_id')
                    ->label('Custodian')
                    ->relationship('custodian', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('currency')
                    ->options(['NGN' => 'NGN', 'USD' => 'USD', 'EUR' => 'EUR']),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('replenish')
                    ->schema([
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('$')
                            ->helperText(fn ($record) => "Current balance: {$record->current_balance}. Imprest: {$record->imprest_amount}."),
                        DatePicker::make('date')
                            ->default(now())
                            ->required(),
                        Textarea::make('notes')
                            ->placeholder('Reason for replenishment'),
                    ])
                    ->action(function (PettyCashFund $record, array $data) {
                        $record->replenish($data['amount']);

                        // Create replenishment transaction
                        $record->transactions()->create([
                            'transaction_number' => app(NumberSeriesService::class)->getNextNo('PC-TRANS'),
                            'date' => $data['date'],
                            'type' => \App\Enums\PettyCashTransactionType::REPLENISHMENT,
                            'amount' => $data['amount'],
                            'running_balance' => $record->current_balance,
                            'description' => $data['notes'] ?? 'Replenishment',
                        ]);
                    })
                    ->visible(fn (PettyCashFund $record) => $record->current_balance < $record->imprest_amount)
                    ->color('success')
                    ->icon('heroicon-m-arrow-up-circle')
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }
}

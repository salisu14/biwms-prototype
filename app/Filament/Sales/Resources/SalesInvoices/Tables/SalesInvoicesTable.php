<?php

namespace App\Filament\Sales\Resources\SalesInvoices\Tables;

use App\Models\SalesInvoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($state) => $state < now() ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('balance_due')
                    ->money('USD')
                    ->sortable()
                    ->color('danger'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'paid' => 'success',
                        'partially_paid' => 'info',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                    ]),
                Filter::make('overdue')
                    ->query(fn ($query) => $query->where('due_date', '<', now())->where('status', '!=', 'paid')),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('record_payment')
                        ->schema([
                            TextInput::make('amount')
                                ->numeric()
                                ->required()
                                ->prefix('$'),
                            DatePicker::make('payment_date')
                                ->default(now())
                                ->required(),
                            Select::make('payment_method')
                                ->options([
                                    'cash' => 'Cash',
                                    'bank_transfer' => 'Bank Transfer',
                                    'check' => 'Check',
                                    'credit_card' => 'Credit Card',
                                ])
                                ->required(),
                        ])
                        ->action(function (SalesInvoice $record, array $data) {
                            $record->recordPayment($data['amount'], $data['payment_date'], $data['payment_method']);
                        })
                        ->visible(fn (SalesInvoice $record) => in_array($record->status, ['open', 'partially_paid', 'overdue']))
                        ->color('success')
                        ->icon('heroicon-m-banknotes'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

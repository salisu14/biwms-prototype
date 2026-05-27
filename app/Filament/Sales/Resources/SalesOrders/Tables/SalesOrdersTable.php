<?php

namespace App\Filament\Sales\Resources\SalesOrders\Tables;

use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesOrdersTable
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
                TextColumn::make('requested_delivery_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'gray',
                        'released' => 'warning',
                        'partially_shipped' => 'info',
                        'shipped' => 'success',
                        'invoiced' => 'success',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'released' => 'Released',
                        'shipped' => 'Shipped',
                    ]),
                Filter::make('document_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('document_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('document_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('release')
                    ->action(function (SalesOrder $record) {
                        $record->update(['status' => 'released']);
                    })
                    ->requiresConfirmation()
                    ->visible(fn (SalesOrder $record) => $record->status === 'open')
                    ->color('warning')
                    ->icon('heroicon-m-arrow-up-on-square'),

                Action::make('create_invoice')
                    ->url(fn (SalesOrder $record) => route('filament.sales.resources.sales-invoices.create', ['sales_order_id' => $record->id]))
                    ->visible(fn (SalesOrder $record) => in_array($record->status, ['shipped', 'partially_shipped']))
                    ->color('success')
                    ->icon('heroicon-m-document-text'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

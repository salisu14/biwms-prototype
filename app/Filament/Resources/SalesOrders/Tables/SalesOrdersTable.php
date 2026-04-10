<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Models\Item;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->money(fn ($record) => $record instanceof SalesOrder ? $record->currency_code : 'NGN')
                    ->sortable()
                    ->alignment('right'),

                IconColumn::make('fully_shipped')
                    ->label('Shipped')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('fully_invoiced')
                    ->label('Invoiced')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('salesperson.name')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SalesOrderStatus::class),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && !$record->isPosted()),

                EditAction::make()
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && !$record->isPosted())
                    ->disabled(fn ($record): bool => $record instanceof SalesOrder && $record->isPosted()),

                // Custom "Reverse" action for posted invoices
                Action::make('reverse')
                    ->label('Reverse')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && $record->isPosted())
                    ->requiresConfirmation()
                    ->action(fn (SalesOrder $record) => $record->reverse()),

                Action::make('printProforma')
                    ->label('Proforma Invoice')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder)
                    ->action(fn (SalesOrder $record) => response()->streamDownload(
                        fn () => print(app(\App\Services\Print\ProformaInvoiceService::class)->generateSalesProforma($record->refresh()->load(['lines']))->output()),
                        $record->order_number . '_Proforma.pdf'
                    )),

                // Super Admin Status Override
                Action::make('changeStatus')
                    ->label('Change Status')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record instanceof SalesOrder && auth()->user()?->hasRole('super_admin'))
                    ->form([
                        Select::make('status')
                            ->options(SalesOrderStatus::class)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (SalesOrder $record, array $data) {
                        $record->update(['status' => $data['status']]);
                        \Filament\Notifications\Notification::make()
                            ->title('Status Updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\MaintenanceContractBillings\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenanceContractBillingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('maintenanceContract.contract_no')
                    ->label('Contract')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->maintenanceContract?->description)
                    ->weight('bold'),

                TextColumn::make('billing_date')
                    ->label('Billing Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->maintenanceContract?->currency_code ?: 'NGN')
                    ->sortable()
                    ->weight('bold')
                    ->alignEnd(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'gray',
                        'invoiced' => 'info',
                        'paid' => 'success',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('purchaseInvoice.document_number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->default('—')
                    ->url(fn ($record) => $record->purchaseInvoice ? route('filament.admin.resources.purchase-invoices.edit', ['record' => $record->purchaseInvoice]) : null),

                TextColumn::make('actual_invoice_date')
                    ->label('Inv. Date')
                    ->date('d/m/Y')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('maintenance_contract_id')
                    ->label('Contract')
                    ->relationship('maintenanceContract', 'contract_no')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'invoiced' => 'Invoiced',
                        'paid' => 'Paid',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('mark_as_invoiced')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Invoiced')
                    ->visible(fn ($record) => $record->status === 'scheduled')
                    ->schema([
                        Select::make('purchase_invoice_id')
                            ->label('Purchase Invoice')
                            ->relationship('purchaseInvoice', 'document_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('actual_invoice_date')
                            ->label('Invoice Date')
                            ->default(now())
                            ->native(false)
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsInvoiced($data['purchase_invoice_id'], $data['actual_invoice_date']);
                        Notification::make()->title('Billing marked as invoiced')->success()->send();
                    }),

                Action::make('mark_as_paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Paid')
                    ->visible(fn ($record) => $record->status === 'invoiced')
                    ->action(function ($record) {
                        $record->markAsPaid();
                        Notification::make()->title('Billing marked as paid')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('billing_date', 'desc');
    }
}

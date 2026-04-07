<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostedSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected static ?string $title = 'Posted Sales Invoices';

    protected static ?string $navigationLabel = 'Posted Invoices';

    // This filters to only posted invoices
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->whereNotNull('posted_at')
            ->latest('posted_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('posted_at')
                    ->label('Posted Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ApprovalStatus $state): string => match ($state) {
                        'posted' => 'success',
                        'approved' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                // Add filters if needed
            ])
            ->recordActions([
                // View only - no edit/delete for posted
                ViewAction::make(),
            ])
            ->toolbarActions([
                // No bulk delete for posted invoices
            ]);
    }

    // Remove create button
    protected function getHeaderActions(): array
    {
        return [
            // No CreateAction here
        ];
    }
}

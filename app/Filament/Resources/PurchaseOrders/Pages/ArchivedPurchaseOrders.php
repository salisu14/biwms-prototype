<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArchivedPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected static ?string $title = 'Archived Purchase Orders';

    protected static ?string $navigationLabel = 'Archived Orders';

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->whereIn('status', [
                PurchaseOrderStatus::INVOICED->value,
                PurchaseOrderStatus::CLOSED->value,
                PurchaseOrderStatus::CANCELLED->value,
            ])
            ->latest('updated_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_date')
                    ->label('Order Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money(fn ($record) => $record->currency_code ?: 'USD')
                    ->sortable()
                    ->weight('bold'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

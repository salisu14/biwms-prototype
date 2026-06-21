<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArchivedSalesOrders extends Page
{
    protected static string $resource = SalesOrderResource::class;

    protected string $view = 'filament.resources.sales-orders.pages.archived-sales-orders';

    protected static ?string $title = 'Archived Sales Orders';

    protected static ?string $navigationLabel = 'Archived Orders';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-archive-box';

    protected static string|null|\UnitEnum $navigationGroup = 'Sales History';

    // Filter to archived/completed orders
    protected function getTableQuery(): Builder
    {
        return SalesOrderResource::getModel()::query()
            ->whereIn('status', [
                SalesOrderStatus::CLOSED->value,
                SalesOrderStatus::CANCELLED->value,
                SalesOrderStatus::INVOICED->value,
            ])
            ->latest('updated_at');
    }
    //    protected function getTableQuery(): Builder
    //    {
    //        return parent::getTableQuery()
    //            ->where(function (Builder $query) {
    //                $query->where('status', 'completed')
    //                    ->orWhere('status', 'closed')
    //                    ->orWhere('status', 'cancelled')
    //                    ->orWhereNotNull('completely_shipped'); // BC style
    //            })
    //            ->latest('updated_at');
    //    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('fully_shipped')
                    ->label('Shipped')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->recordActions([
                ViewAction::make(),
                // No EditAction here - archived orders are read-only
            ])
            ->toolbarActions([]); // No bulk actions for archived
    }

    protected function getHeaderActions(): array
    {
        return []; // No create button
    }
}

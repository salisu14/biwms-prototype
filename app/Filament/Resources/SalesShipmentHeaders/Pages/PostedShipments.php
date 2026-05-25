<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Pages;

use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
use App\Models\SalesShipmentHeader;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostedShipments extends Page
{
    //    use InteractsWithRecord;

    protected static string $resource = SalesShipmentHeaderResource::class;

    protected string $view = 'filament.resources.sales-shipment-headers.pages.posted-shipments';

    protected static ?string $title = 'Posted Shipments';

    protected static ?string $navigationLabel = 'Posted Shipments';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-truck';

    protected static string|null|\UnitEnum $navigationGroup = 'Sales History';

    protected function getTableQuery(): Builder
    {
        return SalesShipmentHeader::query()
            ->whereNotNull('document_no')
            ->whereNotNull('sales_order_id')
            ->withCount('lines')
            ->latest('posting_date');
    }

    //    protected function getTableQuery(): Builder
    //    {
    //        return parent::getTableQuery()
    //            // BC-style: All shipments are "posted" (created via posting)
    //            // Filter to exclude draft/unposted if you have that concept
    //            ->whereNotNull('document_no') // Has document number = posted
    //            ->latest('posting_date');
    //    }
    //    protected function getTableQuery(): Builder
    //    {
    //        return parent::getTableQuery()
    //            ->whereNotNull('posted_at') // If you have posted_at
    //            ->orWhere(function (Builder $query) {
    //                // BC-style: shipments are inherently "posted" when created
    //                // Add additional criteria if needed
    //                $query->whereHas('lines', function ($q) {
    //                    $q->whereNotNull('item_shpt_entry_no'); // Posted to item ledger
    //                });
    //            })
    //            ->latest('posting_date');
    //    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->label('Shipment No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sell_to_customer_name')
                    ->label('Customer')
                    ->searchable(),

                TextColumn::make('order_no')
                    ->label('Order No.')
                    ->searchable(),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('package_tracking_no')
                    ->label('Tracking No.')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('lines_count')
                    ->label('Lines')
                    ->counts('lines'),

                TextColumn::make('ship_to_name')
                    ->label('Ship To')
                    ->toggleable(),
            ])
            ->recordActions([
                ViewAction::make(),
                // No EditAction - posted shipments are read-only
            ])
            ->toolbarActions([]);
    }

    protected function getHeaderActions(): array
    {
        return []; // No create button - shipments are created from orders
    }
}

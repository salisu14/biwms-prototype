<?php

namespace App\Filament\Pages;

use AllowDynamicProperties;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

#[AllowDynamicProperties]
class InventoryValuationReport extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory Valuation Reports';

    protected static ?string $title = 'Inventory Valuation Report';

    protected string $view = 'filament.pages.inventory-valuation-report';

    public $data = [];

    public function mount(): void
    {
        $this->data = [
            'start_date' => now()->startOfMonth(),
            'end_date' => now(),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('start_date')->required(),
            DatePicker::make('end_date')->required(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([

                TextColumn::make('description')->label('Item')->searchable(),

                // OPENING
                TextColumn::make('opening_qty')->label('Opening Qty')->numeric(2),
                TextColumn::make('opening_value')->money('NGN'),

                // PURCHASE
                TextColumn::make('purchase_in_qty')->label('Purchase In'),
                TextColumn::make('purchase_out_qty')->label('Purchase Out'),

                // TRANSFER
                TextColumn::make('transfer_in_qty'),
                TextColumn::make('transfer_out_qty'),

                // ADJUSTMENT
                TextColumn::make('positive_adj_qty'),
                TextColumn::make('negative_adj_qty'),

                // SALES
                TextColumn::make('sales_qty'),
                TextColumn::make('sales_value')->money('NGN'),

                TextColumn::make('sales_return_qty'),

                // CLOSING
                TextColumn::make('closing_qty')->weight('bold'),
                TextColumn::make('closing_value')->money('NGN')->weight('bold'),
            ]);
    }

    protected function getQuery()
    {
        $start = $this->data['start_date'];
        $end = $this->data['end_date'];

        return DB::table('inventory_ledgers as il')
            ->join('items as i', 'i.id', '=', 'il.item_id')

            ->select([
                'i.id as item_id',
                'i.name as description',
                'i.uom',
            ])

            // OPENING
            ->selectRaw("
            SUM(CASE WHEN il.posting_date < ? THEN il.quantity ELSE 0 END) as opening_qty,
            SUM(CASE WHEN il.posting_date < ? THEN il.cost_amount ELSE 0 END) as opening_value
        ", [$start, $start])

            // PURCHASE IN
            ->selectRaw("
            SUM(CASE WHEN il.entry_type = 'purchase' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as purchase_in_qty,
            SUM(CASE WHEN il.entry_type = 'purchase' AND il.posting_date BETWEEN ? AND ? THEN il.cost_amount ELSE 0 END) as purchase_in_value
        ", [$start, $end, $start, $end])

            // PURCHASE OUT
            ->selectRaw("
            SUM(CASE WHEN il.entry_type = 'purchase_return' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as purchase_out_qty,
            SUM(CASE WHEN il.entry_type = 'purchase_return' AND il.posting_date BETWEEN ? AND ? THEN il.cost_amount ELSE 0 END) as purchase_out_value
        ", [$start, $end, $start, $end])

            // TRANSFERS
            ->selectRaw("
            SUM(CASE WHEN il.entry_type = 'transfer_in' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as transfer_in_qty,
            SUM(CASE WHEN il.entry_type = 'transfer_out' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as transfer_out_qty
        ", [$start, $end, $start, $end])

            // ADJUSTMENTS
            ->selectRaw("
            SUM(CASE WHEN il.entry_type = 'positive_adjustment' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as positive_adj_qty,
            SUM(CASE WHEN il.entry_type = 'negative_adjustment' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as negative_adj_qty
        ", [$start, $end, $start, $end])

            // SALES
            ->selectRaw("
            SUM(CASE WHEN il.entry_type = 'sale' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as sales_qty,
            SUM(CASE WHEN il.entry_type = 'sale' AND il.posting_date BETWEEN ? AND ? THEN il.cost_amount ELSE 0 END) as sales_value
        ", [$start, $end, $start, $end])

            // SALES RETURN
            ->selectRaw("
            SUM(CASE WHEN il.entry_type = 'sales_return' AND il.posting_date BETWEEN ? AND ? THEN il.quantity ELSE 0 END) as sales_return_qty
        ", [$start, $end])

            // CLOSING
            ->selectRaw("
            SUM(il.quantity) as closing_qty,
            SUM(il.cost_amount) as closing_value
        ")

            ->groupBy('i.id', 'i.name', 'i.uom');
    }
}

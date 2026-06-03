<?php

namespace App\Filament\Pages;

use App\Models\Item;
use App\Models\Location;
use App\Services\InventoryReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class InventoryValuationReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $title = 'Inventory Movement & Valuation';

    protected string $view = 'filament.pages.inventory-valuation-report';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->startOfMonth()->toDateString(),
            'endDate' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('startDate')
                    ->label('Start Date')
                    ->required()
                    ->live(),
                DatePicker::make('endDate')
                    ->label('End Date')
                    ->required()
                    ->live(),
                Select::make('locationId')
                    ->label('Location')
                    ->options(Location::pluck('name', 'id'))
                    ->placeholder('All Locations')
                    ->live(),
            ])
            ->columns(3)
            ->statePath('formData');
    }

    public function table(Table $table): Table
    {
        $service = app(InventoryReportService::class);
        $state = $this->form->getState();
        $start = Carbon::parse($state['startDate'] ?? $this->formData['startDate'] ?? now()->startOfMonth()->toDateString());
        $end = Carbon::parse($state['endDate'] ?? $this->formData['endDate'] ?? now()->toDateString());
        $locationId = $state['locationId'] ?? $this->formData['locationId'] ?? null;

        return $table
            ->query($service->getMovementSummary($start, $end, $locationId))
            ->columns([
                TextColumn::make('item_code')
                    ->label('Item No.')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Item $record): string => $record->description),

                TextColumn::make('base_unit_of_measure')
                    ->label('UoM'),

                // Opening
                ColumnGroup::make('Opening Balance')
                    ->columns([
                        TextColumn::make('opening_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('opening_value')
                            ->label('Value')
                            ->money("NGN")
                            ->alignRight(),
                    ]),

                // Purchase In
                ColumnGroup::make('Purchase In')
                    ->columns([
                        TextColumn::make('purchase_in_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('purchase_in_value')
                            ->label('Value')
                            ->money('NGN')
                            ->alignRight(),
                    ]),

                // Positive Adjustment
                ColumnGroup::make('Pos. Adj.')
                    ->columns([
                        TextColumn::make('pos_adj_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('pos_adj_value')
                            ->label('Value')
                            ->money('NGN')
                            ->alignRight(),
                    ]),

                ColumnGroup::make('Production Output')
                    ->columns([
                        TextColumn::make('production_output_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('production_output_value')
                            ->label('Value')
                            ->money('NGN')
                            ->alignRight(),
                    ]),

                ColumnGroup::make('Consumption')
                    ->columns([
                        TextColumn::make('production_consumption_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('production_consumption_value')
                            ->label('Value')
                            ->money('NGN')
                            ->alignRight(),
                    ]),

                ColumnGroup::make('Assembly Output')
                    ->columns([
                        TextColumn::make('assembly_output_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('assembly_output_value')
                            ->label('Value')
                            ->money('NGN')
                            ->alignRight(),
                    ]),

                ColumnGroup::make('Assembly Cons.')
                    ->columns([
                        TextColumn::make('assembly_consumption_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('assembly_consumption_value')
                            ->label('Value')
                            ->money('NGN')
                            ->alignRight(),
                    ]),

                // Sales
                ColumnGroup::make('Sales')
                    ->columns([
                        TextColumn::make('sale_out_qty')
                            ->label('Qty')
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('sale_out_value')
                            ->label('Value')
                            ->money("NGN")
                            ->alignRight(),
                    ]),

                // Closing
                ColumnGroup::make('Closing Balance')
                    ->columns([
                        TextColumn::make('closing_qty')
                            ->label('Qty')
                            ->getStateUsing(fn ($record) => $record->opening_qty +
                                $record->purchase_in_qty + $record->purchase_out_qty +
                                $record->pos_adj_qty + $record->neg_adj_qty +
                                $record->production_output_qty + $record->production_consumption_qty +
                                $record->assembly_output_qty + $record->assembly_consumption_qty +
                                $record->sale_out_qty + $record->sale_in_qty +
                                $record->transfer_qty
                            )
                            ->numeric(2)
                            ->alignRight(),
                        TextColumn::make('closing_value')
                            ->label('Value')
                            ->getStateUsing(fn ($record) => $record->opening_value +
                                $record->purchase_in_value + $record->purchase_out_value +
                                $record->pos_adj_value + $record->neg_adj_value +
                                $record->production_output_value + $record->production_consumption_value +
                                $record->assembly_output_value + $record->assembly_consumption_value +
                                $record->sale_out_value + $record->sale_in_value +
                                $record->transfer_value
                            )
                            ->money('NGN')
                            ->alignRight(),
                    ]),
            ])
            ->paginated([50, 100, 200, 'all']);
    }

    public function generateReport(): void
    {
        // Ensure we capture the current form state so table() can use it immediately.
        $this->formData = $this->form->getState();
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}

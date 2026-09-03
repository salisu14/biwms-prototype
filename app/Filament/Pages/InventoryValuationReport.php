<?php

namespace App\Filament\Pages;

use App\Models\Currency;
use App\Models\Location;
use App\Services\Business\BusinessContextService;
use App\Services\Inventory\InventoryValuationReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
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

    public ?int $businessId = null;

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->startOfMonth()->toDateString(),
            'endDate' => now()->toDateString(),
        ]);
        $this->businessId = app(BusinessContextService::class)->resolveId(request()->integer('business_id') ?: null);
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
        // ✅ FIX: Dynamic currency instead of hardcoded "NGN"
        $currency = config('app.currency', 'NGN');
        if (\Illuminate\Support\Facades\Schema::hasTable('currencies')) {
            $currency = Currency::query()->where('is_lcy', true)->value('code') ?? $currency;
        }

        $service = app(InventoryValuationReportService::class);
        $state = $this->form->getState();
        $start = Carbon::parse($state['startDate'] ?? $this->formData['startDate'] ?? now()->startOfMonth()->toDateString());
        $end = Carbon::parse($state['endDate'] ?? $this->formData['endDate'] ?? now()->toDateString());
        $locationId = $state['locationId'] ?? $this->formData['locationId'] ?? null;

        return $table
            ->records(fn (): array => $service->generate($start, $end, [
                'location_id' => $locationId,
                'business_id' => $this->businessId,
            ])->all())
            ->columns([
                TextColumn::make('item_code')
                    ->label('Item No.')
                    ->searchable()
                    ->sortable()
                    ->description(fn (array $record): string => (string) ($record['description'] ?? '')),

                TextColumn::make('base_unit_of_measure')
                    ->label('UoM'),

                TextColumn::make('location_id')
                    ->label('Location'),
                TextColumn::make('quantity_on_hand')
                    ->label('Quantity')
                    ->numeric(4)
                    ->alignRight(),
                TextColumn::make('remaining_quantity')
                    ->label('Remaining')
                    ->numeric(4)
                    ->alignRight(),
                TextColumn::make('expected_cost')
                    ->label('Expected Cost')
                    ->money($currency)
                    ->alignRight(),
                TextColumn::make('actual_cost')
                    ->label('Actual Cost')
                    ->money($currency)
                    ->alignRight(),
                TextColumn::make('adjustment_value')
                    ->label('Adjustments')
                    ->money($currency)
                    ->alignRight(),
                TextColumn::make('inventory_value')
                    ->label('Inventory Value')
                    ->money($currency)
                    ->weight('bold')
                    ->alignRight(),
                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money($currency)
                    ->alignRight(),
            ])
            ->paginated([10, 25, 50]);
    }

    public function generateReport(): void
    {
        $this->formData = $this->form->getState();
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}

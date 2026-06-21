<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Services\Finance\GroupSummaryService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class GroupSummaryReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-table-cells';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?string $title = 'Group Summary / Trial Balance';

    protected string $view = 'filament.pages.finance.group-summary-report';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->startOfYear()->toDateString(),
            'endDate' => now()->toDateString(),
            'category' => '',
            'includeSubLedgers' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(4)->schema([
                    DatePicker::make('startDate')
                        ->label('Start Date')
                        ->required()
                        ->live(),
                    DatePicker::make('endDate')
                        ->label('End Date')
                        ->required()
                        ->live(),
                    Select::make('category')
                        ->label('Group')
                        ->options(fn (): array => $this->categoryOptionsForSelectedPeriod())
                        ->searchable()
                        ->live(),
                    Toggle::make('includeSubLedgers')
                        ->label('Include Ledger Lines')
                        ->inline(false)
                        ->default(true),
                ]),
            ])
            ->statePath('formData');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function generateReport(): void
    {
        // no-op: render-time computation for Livewire payload stability
    }

    public function reportData(): array
    {
        $start = Carbon::parse($this->formData['startDate'] ?? now()->startOfYear()->toDateString());
        $end = Carbon::parse($this->formData['endDate'] ?? now()->toDateString());
        $category = $this->formData['category'] ?: null;
        $includeSubLedgers = (bool) ($this->formData['includeSubLedgers'] ?? true);

        return app(GroupSummaryService::class)->generate($start, $end, $category, $includeSubLedgers);
    }

    /** @return array<string, string> */
    public function categoryOptionsForSelectedPeriod(): array
    {
        try {
            $start = Carbon::parse($this->formData['startDate'] ?? now()->startOfYear()->toDateString());
            $end = Carbon::parse($this->formData['endDate'] ?? now()->toDateString());
        } catch (\Throwable) {
            return app(GroupSummaryService::class)->categoryOptions();
        }

        $service = app(GroupSummaryService::class);
        $options = ['' => 'Trial Balance (All Groups)'];

        foreach ($service->getActiveCategories($start, $end) as $key => $label) {
            $options[$key] = $label;
        }

        return $options;
    }
}

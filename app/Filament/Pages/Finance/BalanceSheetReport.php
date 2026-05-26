<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Services\Finance\BalanceSheetService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class BalanceSheetReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?string $title = 'Balance Sheet';

    protected string $view = 'filament.pages.finance.balance-sheet-report';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'asOfDate' => now()->toDateString(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('asOfDate')
                    ->label('As of Date')
                    ->required(),
            ])
            ->statePath('formData');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function generateReport(): void
    {
        // Intentionally empty: submitting the form re-renders the component,
        // and report data is computed on render to avoid Livewire payload corruption.
    }

    public function reportData(): array
    {
        $asOfDate = Carbon::parse($this->formData['asOfDate'] ?? now()->toDateString());

        return app(BalanceSheetService::class)->generate($asOfDate);
    }
}

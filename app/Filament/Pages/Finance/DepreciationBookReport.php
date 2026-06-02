<?php

declare(strict_types=1);

namespace App\Filament\Pages\Finance;

use App\Models\DepreciationBook;
use App\Models\FAClass;
use App\Models\FAPostingGroup;
use App\Models\Location;
use App\Services\FixedAsset\DepreciationBookReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class DepreciationBookReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Finance';

    protected static ?string $title = 'Depreciation Book Report';

    protected string $view = 'filament.pages.finance.depreciation-book-report';

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'as_of_date' => now()->toDateString(),
            'fa_class_id' => null,
            'location_id' => null,
            'depreciation_book_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('as_of_date')
                    ->label('As of Date'),
                Select::make('fa_class_id')
                    ->label('Class')
                    ->options(FAClass::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('All classes')
                    ->searchable()
                    ->preload(),
                Select::make('location_id')
                    ->label('Location')
                    ->options(Location::query()->active()->orderBy('name')->pluck('name', 'id'))
                    ->placeholder('All locations')
                    ->searchable()
                    ->preload(),
                Select::make('depreciation_book_id')
                    ->label('Book')
                    ->options(DepreciationBook::query()->where('is_active', true)->orderBy('code')->pluck('code', 'id'))
                    ->placeholder('All books')
                    ->searchable()
                    ->preload(),
            ])
            ->statePath('formData');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function generateReport(): void
    {
        // Re-render only.
    }

    public function reportData(): array
    {
        return app(DepreciationBookReportService::class)->generate($this->formData ?? []);
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function activeFilters(): array
    {
        $filters = [];

        if (filled($this->formData['as_of_date'] ?? null)) {
            $filters[] = ['label' => 'As of Date', 'value' => (string) $this->formData['as_of_date']];
        }

        if (filled($this->formData['fa_class_id'] ?? null)) {
            $filters[] = [
                'label' => 'Class',
                'value' => (string) (FAClass::query()->find($this->formData['fa_class_id'])?->name ?? $this->formData['fa_class_id']),
            ];
        }

        if (filled($this->formData['location_id'] ?? null)) {
            $filters[] = [
                'label' => 'Location',
                'value' => (string) (Location::query()->find($this->formData['location_id'])?->name ?? $this->formData['location_id']),
            ];
        }

        if (filled($this->formData['depreciation_book_id'] ?? null)) {
            $filters[] = [
                'label' => 'Book',
                'value' => (string) (DepreciationBook::query()->find($this->formData['depreciation_book_id'])?->code ?? $this->formData['depreciation_book_id']),
            ];
        }

        return $filters;
    }

    /**
     * @return array{show: bool, message: string}
     */
    public function setupWarning(): array
    {
        $missingParts = [];

        if (FAPostingGroup::query()->count() === 0) {
            $missingParts[] = 'FA Posting Groups';
        }

        if (DepreciationBook::query()->count() === 0) {
            $missingParts[] = 'Depreciation Books';
        }

        if (FAClass::query()->count() === 0) {
            $missingParts[] = 'FA Classes';
        }

        return [
            'show' => $missingParts !== [],
            'message' => $missingParts === []
                ? ''
                : 'The fixed asset setup is incomplete. Missing: '.implode(', ', $missingParts).'. Seed or create them before relying on this report.',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\AdminPages\PurchaseThreeWayMatch as AdminPurchaseThreeWayMatch;
use App\Filament\Pages\Finance\PurchaseThreeWayMatch as FinancePurchaseThreeWayMatch;
use App\Models\Vendor;
use App\Services\Company\CompanyInformationService;
use App\Services\Purchase\PurchaseThreeWayMatchService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

abstract class PurchaseThreeWayMatchPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Purchase Three-Way Match';

    protected static ?string $title = 'Purchase Three-Way Match';

    protected static ?string $slug = 'purchase-three-way-match';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.purchases.purchase-three-way-match';

    public ?int $business_id = null;

    public ?int $vendor_id = null;

    public ?int $purchase_order_id = null;

    public ?string $match_status = null;

    public ?string $date_from = null;

    public ?string $date_to = null;

    public function mount(): void
    {
        $this->business_id = app(CompanyInformationService::class)->resolveBusinessId(request()->integer('business_id') ?: null);
        $this->vendor_id = request()->integer('vendor_id') ?: null;
        $this->purchase_order_id = request()->integer('purchase_order_id') ?: null;
        $this->match_status = request()->filled('match_status') ? (string) request()->query('match_status') : null;
        $this->date_from = request()->filled('date_from') ? (string) request()->query('date_from') : null;
        $this->date_to = request()->filled('date_to') ? (string) request()->query('date_to') : null;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Analytics';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('purchasing.purchase_three_way_match.view') === true;
    }

    public static function urlForCurrentPanel(array $parameters = []): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'finance';
        $pageClass = $panelId === 'admin'
            ? AdminPurchaseThreeWayMatch::class
            : FinancePurchaseThreeWayMatch::class;

        return $pageClass::getUrl(panel: $panelId, parameters: $parameters);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Three-Way Match Filters')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(fn (): array => Vendor::query()
                            ->orderBy('vendor_name')
                            ->limit(250)
                            ->get()
                            ->mapWithKeys(fn (Vendor $vendor): array => [
                                $vendor->id => trim(($vendor->vendor_code ? "{$vendor->vendor_code} - " : '').$vendor->vendor_name),
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('purchase_order_id')
                        ->label('Purchase Order')
                        ->options(fn (): array => $this->purchaseOrderOptions())
                        ->searchable()
                        ->preload()
                        ->placeholder('All purchase orders')
                        ->live(onBlur: true),
                    Select::make('match_status')
                        ->label('Match Status')
                        ->options([
                            'Matched' => 'Matched',
                            'Partially Received' => 'Partially Received',
                            'Partially Invoiced' => 'Partially Invoiced',
                            'Price Variance' => 'Price Variance',
                            'Quantity Variance' => 'Quantity Variance',
                            'Over Received' => 'Over Received',
                            'Over Invoiced' => 'Over Invoiced',
                            'Direct Invoice / No Receipt Match' => 'Direct Invoice / No Receipt Match',
                        ])
                        ->placeholder('All statuses')
                        ->searchable()
                        ->live(),
                    DatePicker::make('date_from')
                        ->label('From Date')
                        ->live(onBlur: true),
                    DatePicker::make('date_to')
                        ->label('To Date')
                        ->afterOrEqual('date_from')
                        ->live(onBlur: true),
                ]),
        ]);
    }

    public function refreshReport(): void
    {
        Notification::make()
            ->title('Purchase three-way match refreshed')
            ->success()
            ->send();
    }

    public function resetFilters(): void
    {
        $this->business_id = app(CompanyInformationService::class)->resolveBusinessId();
        $this->vendor_id = null;
        $this->purchase_order_id = null;
        $this->match_status = null;
        $this->date_from = null;
        $this->date_to = null;
    }

    public function getViewData(): array
    {
        $report = app(PurchaseThreeWayMatchService::class)->generate($this->filters());

        return [
            'report' => $report,
            'csvUrl' => route('reports.purchase-three-way-match.export', [
                ...$this->filters(),
                'format' => 'csv',
            ]),
            'pdfUrl' => route('reports.purchase-three-way-match.export', [
                ...$this->filters(),
                'format' => 'pdf',
            ]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshReport'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return array_filter([
            'business_id' => $this->business_id,
            'vendor_id' => $this->vendor_id,
            'purchase_order_id' => $this->purchase_order_id,
            'match_status' => $this->match_status,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ], fn (mixed $value): bool => filled($value));
    }

    /**
     * @return array<int, string>
     */
    protected function purchaseOrderOptions(): array
    {
        return app(PurchaseThreeWayMatchService::class)
            ->generate($this->filters())['rows']
            ->pluck('reference_number')
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $referenceNumber): array => [$referenceNumber => $referenceNumber])
            ->all();
    }
}

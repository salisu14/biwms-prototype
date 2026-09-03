<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\AdminPages\VendorSettlementHistory as AdminVendorSettlementHistory;
use App\Filament\Pages\Finance\VendorSettlementHistory as FinanceVendorSettlementHistory;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PurchaseCreditMemos\PurchaseCreditMemoResource;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Business;
use App\Models\Currency;
use App\Models\Vendor;
use App\Services\Business\BusinessContextService;
use App\Services\Finance\VendorSettlementHistoryService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class VendorSettlementHistoryPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Vendor Settlement History';

    protected static ?string $title = 'Vendor Settlement History';

    protected static ?string $slug = 'vendor-settlement-history';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.finance.vendor-settlement-history';

    public ?int $vendor_id = null;

    public ?string $settlement_type = null;

    public ?string $source_document_number = null;

    public ?string $target_document_number = null;

    public ?string $date_from = null;

    public ?string $date_to = null;

    public ?string $currency_code = null;

    public ?int $business_id = null;

    public function mount(): void
    {
        $this->vendor_id = request()->integer('vendor_id') ?: (request()->integer('vendorId') ?: null);
        $this->settlement_type = request()->filled('settlement_type') ? (string) request()->query('settlement_type') : null;
        $this->source_document_number = request()->filled('source_document_number') ? (string) request()->query('source_document_number') : null;
        $this->target_document_number = request()->filled('target_document_number') ? (string) request()->query('target_document_number') : null;
        $this->date_from = request()->filled('date_from') ? (string) request()->query('date_from') : null;
        $this->date_to = request()->filled('date_to') ? (string) request()->query('date_to') : null;
        $this->currency_code = request()->filled('currency_code') ? (string) request()->query('currency_code') : null;
        $this->business_id = app(BusinessContextService::class)->resolveId(request()->integer('business_id') ?: null);
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Analytics';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('finance.vendor_settlement_history.view') === true;
    }

    public static function urlForCurrentPanel(array $parameters = []): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'finance';
        $pageClass = $panelId === 'admin'
            ? AdminVendorSettlementHistory::class
            : FinanceVendorSettlementHistory::class;

        return $pageClass::getUrl(panel: $panelId, parameters: $parameters);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Settlement Filters')
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
                    Select::make('settlement_type')
                        ->label('Settlement Type')
                        ->options([
                            'PAYMENT_APPLICATION' => 'Payment Application',
                            'CREDIT_MEMO_APPLICATION' => 'Purchase Credit Memo Application',
                        ])
                        ->placeholder('All settlement types')
                        ->searchable()
                        ->live(),
                    Select::make('source_document_number')
                        ->label('Source Document')
                        ->options(fn (): array => $this->sourceDocumentOptions())
                        ->searchable()
                        ->preload()
                        ->placeholder('All source documents')
                        ->live(onBlur: true),
                    TextInput::make('target_document_number')
                        ->label('Target Invoice')
                        ->placeholder('P-INV-...')
                        ->live(onBlur: true),
                    DatePicker::make('date_from')
                        ->label('Date From')
                        ->live(onBlur: true),
                    DatePicker::make('date_to')
                        ->label('Date To')
                        ->afterOrEqual('date_from')
                        ->live(onBlur: true),
                    Select::make('currency_code')
                        ->label('Currency')
                        ->options(fn (): array => $this->currencyOptions())
                        ->searchable()
                        ->preload()
                        ->placeholder('All currencies')
                        ->live(onBlur: true),
                    Select::make('business_id')
                        ->label('Business')
                        ->options(fn (): array => Business::query()
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(fn (Business $business): array => [
                                $business->id => trim(($business->code ? "{$business->code} - " : '').$business->name),
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                ]),
        ]);
    }

    public function refreshReport(): void
    {
        Notification::make()
            ->title('Settlement history refreshed')
            ->success()
            ->send();
    }

    public function resetFilters(): void
    {
        $this->vendor_id = null;
        $this->settlement_type = null;
        $this->source_document_number = null;
        $this->target_document_number = null;
        $this->date_from = null;
        $this->date_to = null;
        $this->currency_code = null;
        $this->business_id = app(BusinessContextService::class)->resolveId();
    }

    public function getViewData(): array
    {
        return [
            'rows' => $this->settlements(),
            'csvUrl' => route('reports.vendor-settlement-history.export', [
                ...$this->filters(),
                'format' => 'csv',
            ]),
            'pdfUrl' => route('reports.vendor-settlement-history.export', [
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

    public function settlements(): LengthAwarePaginator
    {
        return app(VendorSettlementHistoryService::class)->paginate($this->filters());
    }

    public function vendorUrl(?int $vendorId): ?string
    {
        return $vendorId
            ? VendorResource::getUrl(
                'view',
                parameters: ['record' => $vendorId],
                panel: 'admin',
            )
            : null;
    }

    public function sourceDocumentUrl(object $row): ?string
    {
        return match ($row->source_document_type) {
            'PAYMENT' => $row->source_document_id ? PaymentResource::getUrl('view', ['record' => $row->source_document_id], panel: 'admin') : null,
            'PURCHASE_CREDIT_MEMO' => $row->source_document_id ? PurchaseCreditMemoResource::getUrl('view', ['record' => $row->source_document_id], panel: 'admin') : null,
            default => null,
        };
    }

    public function targetDocumentUrl(object $row): ?string
    {
        return $row->target_document_id
            ? PurchaseInvoiceResource::getUrl('view-posted', ['record' => $row->target_document_id], panel: 'admin')
            : null;
    }

    /**
     * @return array<string, string>
     */
    public function sourceDocumentOptions(): array
    {
        $filters = $this->filters();
        unset($filters['source_document_number']);

        return app(VendorSettlementHistoryService::class)
            ->rows($filters)
            ->mapWithKeys(function (object $row): array {
                if (! filled($row->source_document_number)) {
                    return [];
                }

                return [
                    (string) $row->source_document_number => $this->sourceDocumentLabel($row),
                ];
            })
            ->sortKeys()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function currencyOptions(): array
    {
        return Currency::query()
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Currency $currency): array => [
                $currency->code => trim($currency->code.' — '.($currency->description ?: $currency->code)),
            ])
            ->all();
    }

    public function sourceDocumentLabel(object $row): string
    {
        return match ($row->source_document_type) {
            'PAYMENT' => trim(($row->source_document_number ?? '—').' — Payment'),
            'PURCHASE_CREDIT_MEMO' => trim(($row->source_document_number ?? '—').' — Purchase Credit Memo'),
            default => trim(($row->source_document_number ?? '—').' — '.str_replace('_', ' ', (string) $row->source_document_type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter([
            'vendor_id' => $this->vendor_id,
            'settlement_type' => $this->settlement_type,
            'source_document_number' => $this->source_document_number,
            'target_document_number' => $this->target_document_number,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'currency_code' => $this->currency_code ? strtoupper($this->currency_code) : null,
            'business_id' => $this->business_id,
        ], fn (mixed $value): bool => filled($value));
    }
}

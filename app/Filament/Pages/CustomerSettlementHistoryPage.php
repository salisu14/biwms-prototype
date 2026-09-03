<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\AdminPages\CustomerSettlementHistory as AdminCustomerSettlementHistory;
use App\Filament\Pages\Finance\CustomerSettlementHistory as FinanceCustomerSettlementHistory;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\Business;
use App\Models\Currency;
use App\Models\Customer;
use App\Services\Company\CompanyInformationService;
use App\Services\Finance\CustomerSettlementHistoryService;
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

abstract class CustomerSettlementHistoryPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Customer Settlement History';

    protected static ?string $title = 'Customer Settlement History';

    protected static ?string $slug = 'customer-settlement-history';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.finance.customer-settlement-history';

    public ?int $customer_id = null;

    public ?string $settlement_type = null;

    public ?string $source_document_number = null;

    public ?string $target_document_number = null;

    public ?string $date_from = null;

    public ?string $date_to = null;

    public ?string $currency_code = null;

    public ?int $business_id = null;

    public function mount(): void
    {
        $this->customer_id = request()->integer('customer_id') ?: (request()->integer('customerId') ?: null);
        $this->settlement_type = request()->filled('settlement_type') ? (string) request()->query('settlement_type') : null;
        $this->source_document_number = request()->filled('source_document_number') ? (string) request()->query('source_document_number') : null;
        $this->target_document_number = request()->filled('target_document_number') ? (string) request()->query('target_document_number') : null;
        $this->date_from = request()->filled('date_from') ? (string) request()->query('date_from') : null;
        $this->date_to = request()->filled('date_to') ? (string) request()->query('date_to') : null;
        $this->currency_code = request()->filled('currency_code') ? (string) request()->query('currency_code') : null;
        $this->business_id = app(CompanyInformationService::class)->resolveBusinessId(
            request()->integer('business_id') ?: null,
        );
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports & Analytics';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('finance.customer_settlement_history.view') === true;
    }

    public static function urlForCurrentPanel(array $parameters = []): string
    {
        $panelId = Filament::getCurrentPanel()?->getId() ?? 'finance';
        $pageClass = $panelId === 'admin'
            ? AdminCustomerSettlementHistory::class
            : FinanceCustomerSettlementHistory::class;

        return $pageClass::getUrl(panel: $panelId, parameters: $parameters);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Settlement Filters')
                ->columns(['default' => 1, 'md' => 2, 'xl' => 4])
                ->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->options(fn (): array => Customer::query()
                            ->orderBy('name')
                            ->limit(250)
                            ->get()
                            ->mapWithKeys(fn (Customer $customer): array => [
                                $customer->id => trim(($customer->customer_number ? "{$customer->customer_number} - " : '').$customer->name),
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('settlement_type')
                        ->label('Settlement Type')
                        ->options([
                            'PAYMENT_APPLICATION' => 'Payment Application',
                            'CREDIT_MEMO_APPLICATION' => 'Sales Credit Memo Application',
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
                        ->placeholder('S-INV-...')
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
        $this->customer_id = null;
        $this->settlement_type = null;
        $this->source_document_number = null;
        $this->target_document_number = null;
        $this->date_from = null;
        $this->date_to = null;
        $this->currency_code = null;
        $this->business_id = null;
    }

    public function getViewData(): array
    {
        return [
            'rows' => $this->settlements(),
            'csvUrl' => route('reports.customer-settlement-history.export', [
                ...$this->filters(),
                'format' => 'csv',
            ]),
            'pdfUrl' => route('reports.customer-settlement-history.export', [
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
        return app(CustomerSettlementHistoryService::class)->paginate($this->filters());
    }

    public function customerUrl(?int $customerId): ?string
    {
        return $customerId
            ? CustomerResource::getUrl(
                'view',
                parameters: ['record' => $customerId],
                panel: 'admin',
            )
            : null;
    }

    public function sourceDocumentUrl(object $row): ?string
    {
        return match ($row->source_document_type) {
            'PAYMENT' => $row->source_document_id ? PaymentResource::getUrl('view', ['record' => $row->source_document_id]) : null,
            'SALES_CREDIT_MEMO' => $row->source_document_id ? SalesInvoiceResource::getUrl('view-posted-credit-memo', ['record' => $row->source_document_id]) : null,
            default => null,
        };
    }

    public function targetDocumentUrl(object $row): ?string
    {
        return $row->target_document_id
            ? SalesInvoiceResource::getUrl('view-posted', ['record' => $row->target_document_id])
            : null;
    }

    /**
     * @return array<string, string>
     */
    public function sourceDocumentOptions(): array
    {
        $filters = $this->filters();
        unset($filters['source_document_number']);

        return app(CustomerSettlementHistoryService::class)
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
            'SALES_CREDIT_MEMO' => trim(($row->source_document_number ?? '—').' — Sales Credit Memo'),
            default => trim(($row->source_document_number ?? '—').' — '.str_replace('_', ' ', (string) $row->source_document_type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return array_filter([
            'customer_id' => $this->customer_id,
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

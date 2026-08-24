<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Exceptions\BusinessException;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\CustomerLedgerApplication;
use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Throwable;

class ViewPostedSalesCreditMemo extends Page
{
    protected static string $resource = SalesInvoiceResource::class;

    protected string $view = 'filament.resources.sales-invoices.pages.view-posted-sales-credit-memo';

    protected static ?string $title = 'Posted Sales Credit Memo';

    public PostedSalesCreditMemo $record;

    public static function canAccess(array $parameters = []): bool
    {
        return SalesInvoiceResource::canAccessPostedInvoiceHistory();
    }

    public function mount(PostedSalesCreditMemo|int|string $record): void
    {
        if ($record instanceof PostedSalesCreditMemo) {
            $this->record = $record->load(['lines', 'customer', 'correctedInvoice', 'salesOrder', 'location']);

            return;
        }

        $this->record = PostedSalesCreditMemo::query()
            ->with(['lines', 'customer', 'correctedInvoice', 'salesOrder', 'location'])
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        $customer = $this->record->customer?->customer_name ?? $this->record->customer?->name ?? 'Unknown Customer';
        $amount = Number::currency((float) $this->record->grand_total, $this->record->currency_code ?: config('app.default_currency', 'USD'));

        return ($this->record->document_number ?? 'Posted Sales Credit Memo')
            .' • '.$customer
            .' • '.$amount;
    }

    public function getSubheading(): string
    {
        $location = $this->record->location?->code
            ? "{$this->record->location->code} - {$this->record->location->name}"
            : ($this->record->location?->name ?? 'Unknown Location');

        return trim(implode(' • ', array_filter([
            $this->record->correctedInvoice?->invoice_number ?: 'No corrected invoice',
            $location,
            'Posted '.optional($this->record->posting_date)->format('d/m/Y'),
        ])));
    }

    public function getBreadcrumb(): string
    {
        $customer = $this->record->customer?->customer_name ?? $this->record->customer?->name ?? 'Unknown Customer';

        return ($this->record->document_number ?? 'Posted Sales Credit Memo').' - '.$customer;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('applyCreditMemo')
                ->label('Apply to Documents')
                ->icon('heroicon-o-document-check')
                ->color('primary')
                ->visible(fn (): bool => $this->canApplyCreditMemo())
                ->schema([
                    TextInput::make('customer_display')
                        ->label('Customer')
                        ->default(fn (): string => $this->record->customer_name ?: ($this->record->customer?->name ?? 'Unknown Customer'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('credit_memo_display')
                        ->label('Credit Memo')
                        ->default(fn (): string => $this->record->document_number ?? 'Posted Sales Credit Memo')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('available_credit_display')
                        ->label('Available Credit')
                        ->default(fn (): string => $this->formatMoney((float) $this->record->remaining_amount))
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('document_type')
                        ->label('Target Document Type')
                        ->options(['SALES_INVOICE' => 'Sales Invoice'])
                        ->default('SALES_INVOICE')
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('target_invoice_id')
                        ->label('Target Posted Sales Invoice')
                        ->options(fn (): array => $this->eligiblePostedInvoiceOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->helperText('Only open posted sales invoices for this customer, currency, and business are available.'),
                    TextInput::make('invoice_date_display')
                        ->label('Invoice Date')
                        ->formatStateUsing(fn ($state, $get): string => $this->selectedInvoiceSnapshot($get('target_invoice_id'))['invoice_date'])
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('invoice_total_display')
                        ->label('Original Invoice Amount')
                        ->formatStateUsing(fn ($state, $get): string => $this->selectedInvoiceSnapshot($get('target_invoice_id'))['original_amount'])
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('invoice_remaining_display')
                        ->label('Invoice Remaining')
                        ->formatStateUsing(fn ($state, $get): string => $this->selectedInvoiceSnapshot($get('target_invoice_id'))['remaining_amount'])
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('amount')
                        ->label('Amount to Apply')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->default(fn (): ?float => $this->defaultApplicationAmount())
                        ->rule(function ($get): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                $amount = round((float) $value, 4);
                                $limit = $this->applicationLimitForInvoice($get('target_invoice_id'));

                                if ($amount <= 0.0001) {
                                    $fail('Application amount must be greater than zero.');

                                    return;
                                }

                                if ($limit !== null && $amount - $limit > 0.0001) {
                                    $fail('Application amount cannot exceed the available credit or selected invoice remaining amount.');
                                }
                            };
                        })
                        ->helperText(fn ($get): string => $this->amountHelperText($get('target_invoice_id'))),
                ])
                ->modalHeading('Apply Sales Credit Memo')
                ->modalSubmitActionLabel('Apply Credit')
                ->action(function (array $data): void {
                    $this->applyCreditMemoToInvoice($data);
                }),
            Action::make('back')
                ->label('Back to Posted Invoices')
                ->color('gray')
                ->url(SalesInvoiceResource::getUrl('posted')),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function eligiblePostedInvoiceOptions(): array
    {
        return $this->eligiblePostedInvoices()
            ->mapWithKeys(fn (PostedSalesInvoice $invoice): array => [
                $invoice->id => trim(implode(' • ', array_filter([
                    $invoice->document_number,
                    optional($invoice->posting_date)->format('Y-m-d'),
                    'Remaining '.$this->formatMoney((float) $invoice->remaining_amount),
                ]))),
            ])
            ->all();
    }

    public function canApplyCreditMemo(): bool
    {
        $user = auth()->user();

        if (! $user?->can('sales.credit_memo.apply')) {
            return false;
        }

        if ($this->record->corrected || $this->record->refunded || $this->record->fully_applied) {
            return false;
        }

        if ((float) $this->record->remaining_amount <= 0.0001) {
            return false;
        }

        $sourceLedgerEntry = $this->sourceCustomerLedgerEntry();

        return $sourceLedgerEntry !== null
            && ! $sourceLedgerEntry->reversed
            && (bool) $sourceLedgerEntry->open
            && (float) $sourceLedgerEntry->remaining_amount > 0.0001;
    }

    public function defaultApplicationAmount(?int $invoiceId = null): ?float
    {
        $invoiceId ??= (int) array_key_first($this->eligiblePostedInvoiceOptions());

        return $this->applicationLimitForInvoice($invoiceId);
    }

    /**
     * @return array{invoice_date: string, original_amount: string, remaining_amount: string}
     */
    public function selectedInvoiceSnapshot(mixed $invoiceId): array
    {
        $invoice = is_numeric($invoiceId)
            ? $this->eligiblePostedInvoices()->firstWhere('id', (int) $invoiceId)
            : null;

        if (! $invoice instanceof PostedSalesInvoice) {
            return [
                'invoice_date' => 'Select a posted sales invoice',
                'original_amount' => '—',
                'remaining_amount' => '—',
            ];
        }

        return [
            'invoice_date' => optional($invoice->posting_date)->format('Y-m-d') ?: '—',
            'original_amount' => $this->formatMoney((float) $invoice->grand_total),
            'remaining_amount' => $this->formatMoney((float) $invoice->remaining_amount),
        ];
    }

    public function applicationLimitForInvoice(mixed $invoiceId): ?float
    {
        if (! is_numeric($invoiceId)) {
            return null;
        }

        $invoice = $this->eligiblePostedInvoices()->firstWhere('id', (int) $invoiceId);

        if (! $invoice instanceof PostedSalesInvoice) {
            return null;
        }

        return round(min((float) $this->record->remaining_amount, (float) $invoice->remaining_amount), 4);
    }

    public function amountHelperText(mixed $invoiceId): string
    {
        $limit = $this->applicationLimitForInvoice($invoiceId);

        if ($limit === null) {
            return 'Select an open posted sales invoice to calculate the maximum applicable amount.';
        }

        return 'Maximum applicable amount: '.$this->formatMoney($limit).'.';
    }

    /**
     * @return Collection<int, PostedSalesInvoice>
     */
    private function eligiblePostedInvoices(): Collection
    {
        $creditMemoBusinessId = $this->businessIdForDocument($this->record);
        $creditMemoCurrencyCode = $this->record->currency_code ?: null;
        $creditMemoCurrencyId = $this->record->currency_id ?? null;

        return PostedSalesInvoice::query()
            ->where('customer_id', $this->record->customer_id)
            ->where('remaining_amount', '>', 0)
            ->where('cancelled', false)
            ->orderByDesc('posting_date')
            ->orderByDesc('id')
            ->get()
            ->filter(function (PostedSalesInvoice $invoice) use ($creditMemoBusinessId, $creditMemoCurrencyCode, $creditMemoCurrencyId): bool {
                if (($invoice->currency_code ?: null) !== $creditMemoCurrencyCode) {
                    return false;
                }

                if ($creditMemoCurrencyId && ($invoice->currency_id ?? null) && (int) $creditMemoCurrencyId !== (int) $invoice->currency_id) {
                    return false;
                }

                $invoiceBusinessId = $this->businessIdForDocument($invoice);

                if ($creditMemoBusinessId !== null && $invoiceBusinessId !== null && $creditMemoBusinessId !== $invoiceBusinessId) {
                    return false;
                }

                $invoiceLedgerEntry = $this->invoiceCustomerLedgerEntry($invoice);

                return $invoiceLedgerEntry !== null
                    && ! $invoiceLedgerEntry->reversed
                    && (bool) $invoiceLedgerEntry->open
                    && (float) $invoiceLedgerEntry->remaining_amount > 0.0001;
            })
            ->values();
    }

    private function sourceCustomerLedgerEntry(): ?CustomerLedgerEntry
    {
        return CustomerLedgerEntry::query()
            ->where('customer_id', $this->record->customer_id)
            ->where('document_type', 'SALES_CREDIT_MEMO')
            ->where('source_type', PostedSalesCreditMemo::class)
            ->where('source_id', $this->record->id)
            ->first();
    }

    private function invoiceCustomerLedgerEntry(PostedSalesInvoice $invoice): ?CustomerLedgerEntry
    {
        return CustomerLedgerEntry::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('document_type', 'SALES_INVOICE')
            ->where('source_type', PostedSalesInvoice::class)
            ->where('source_id', $invoice->id)
            ->first();
    }

    private function businessIdForDocument(PostedSalesCreditMemo|PostedSalesInvoice $document): ?int
    {
        $dimensions = $document->dimensions;

        if (! is_array($dimensions)) {
            return null;
        }

        $businessId = $dimensions['business_id'] ?? $dimensions['business'] ?? null;

        return is_numeric($businessId) ? (int) $businessId : null;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    private function applyCreditMemoToInvoice(array $data): void
    {
        if (! $this->canApplyCreditMemo()) {
            throw new AuthorizationException('You are not allowed to apply this sales credit memo.');
        }

        try {
            $this->record->applyToInvoices([
                [
                    'invoice_id' => (int) ($data['target_invoice_id'] ?? 0),
                    'amount' => round((float) ($data['amount'] ?? 0), 4),
                ],
            ]);

            $this->record = $this->record->fresh(['lines', 'customer', 'correctedInvoice', 'salesOrder', 'location']);

            Notification::make()
                ->title('Credit memo applied')
                ->body('The sales credit memo was applied to the selected posted sales invoice.')
                ->success()
                ->send();
        } catch (BusinessException $exception) {
            Notification::make()
                ->title('Credit memo was not applied')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $exception) {
            Log::error('Posted sales credit memo application failed.', [
                'posted_sales_credit_memo_id' => $this->record->id,
                'target_invoice_id' => $data['target_invoice_id'] ?? null,
                'exception' => $exception,
            ]);

            Notification::make()
                ->title('Credit memo was not applied')
                ->body('An unexpected error occurred while applying the credit memo. Please review the logs and try again.')
                ->danger()
                ->send();
        }
    }

    private function formatMoney(float $amount): string
    {
        return Number::currency($amount, $this->record->currency_code ?: config('app.default_currency', 'NGN'));
    }

    public function getApplicationsProperty(): Collection
    {
        $ledgerApplications = CustomerLedgerApplication::query()
            ->with(['targetInvoice', 'applier'])
            ->where('source_posted_sales_credit_memo_id', $this->record->id)
            ->where('reversed', false)
            ->orderByDesc('applied_at')
            ->get()
            ->map(fn (CustomerLedgerApplication $application): array => [
                'entry_id' => $application->target_customer_ledger_entry_id,
                'document_number' => $application->targetInvoice?->document_number,
                'amount' => (float) $application->amount,
                'applied_at' => optional($application->applied_at)->toDateTimeString(),
                'applied_by' => $application->applier?->name,
                'invoice_record_id' => $application->target_posted_sales_invoice_id,
                'source_remaining_before' => (float) $application->source_remaining_before,
                'source_remaining_after' => (float) $application->source_remaining_after,
                'target_remaining_before' => (float) $application->target_remaining_before,
                'target_remaining_after' => (float) $application->target_remaining_after,
                'currency_code' => $application->currency_code,
                'application_reference' => $application->idempotency_key ? substr($application->idempotency_key, 0, 12) : null,
                'trace_type' => CustomerLedgerApplication::class,
            ]);

        if ($ledgerApplications->isNotEmpty()) {
            return $ledgerApplications->values();
        }

        $creditMemoEntry = CustomerLedgerEntry::query()
            ->where('source_type', PostedSalesCreditMemo::class)
            ->where('source_id', $this->record->id)
            ->first();

        return collect($creditMemoEntry?->applied_to_entries ?? [])
            ->map(function (array $application): array {
                $invoiceLedgerEntry = CustomerLedgerEntry::query()->find($application['entry_id'] ?? null);
                $invoiceRecordId = null;

                if ($invoiceLedgerEntry?->source_type === PostedSalesInvoice::class) {
                    $invoiceRecordId = $invoiceLedgerEntry->source_id;
                }

                return [
                    ...$application,
                    'invoice_record_id' => $invoiceRecordId,
                ];
            })
            ->values();
    }
}

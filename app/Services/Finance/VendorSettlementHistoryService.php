<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class VendorSettlementHistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $rows = $this->rows($filters);
        $page = Paginator::resolveCurrentPage() ?: 1;

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function rows(array $filters = []): Collection
    {
        $paymentRows = $this->paymentApplicationRows($filters);
        $creditMemoRows = $this->creditMemoApplicationRows($filters);

        return $this->applyFilters(
            $paymentRows
                ->concat($creditMemoRows)
                ->sortBy([
                    ['application_date', 'desc'],
                    ['settlement_id', 'desc'],
                ])
                ->values(),
            $filters,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function paymentApplicationRows(array $filters): Collection
    {
        $applications = PaymentApplication::query()
            ->with(['payment'])
            ->active()
            ->where('document_type', 'PURCHASE_INVOICE')
            ->when(filled($filters['business_id'] ?? null), fn ($query, int $businessId) => $query->whereHas('payment', fn ($paymentQuery) => $paymentQuery->where('business_id', $businessId)))
            ->get()
            ->filter(function (PaymentApplication $application) use ($filters): bool {
                if ($application->payment?->party_type !== 'VENDOR') {
                    return false;
                }

                if (! filled($filters['business_id'] ?? null)) {
                    return true;
                }

                $businessId = (int) $filters['business_id'];
                $paymentBusinessId = (int) ($application->payment?->business_id ?? 0);

                return $paymentBusinessId === $businessId;
            })
            ->values();

        $paymentIds = $applications->pluck('payment_id')->filter()->unique()->values();
        $vendorIds = $applications->pluck('payment.party_id')->filter()->unique()->values();
        $invoiceIds = $applications->pluck('document_id')->filter()->unique()->values();

        $vendorsById = Vendor::query()
            ->whereIn('id', $vendorIds)
            ->get()
            ->keyBy('id');

        $sourceLedgerByPaymentId = VendorLedgerEntry::query()
            ->where('document_type', 'PAYMENT')
            ->where('source_type', Payment::class)
            ->whereIn('source_id', $paymentIds)
            ->get()
            ->keyBy('source_id');

        $documentsById = PostedPurchaseInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->get()
            ->keyBy('id');

        $targetLedgerByInvoiceId = VendorLedgerEntry::query()
            ->where('document_type', 'PURCHASE_INVOICE')
            ->whereIn('source_id', $invoiceIds)
            ->where('source_type', PurchaseInvoice::class)
            ->get()
            ->keyBy('source_id');

        return $applications->map(function (PaymentApplication $application) use ($sourceLedgerByPaymentId, $targetLedgerByInvoiceId, $documentsById, $vendorsById): object {
            $payment = $application->payment;
            $document = $documentsById->get($application->document_id);
            $vendor = $vendorsById->get($payment?->party_id);

            return (object) [
                'settlement_type' => 'PAYMENT_APPLICATION',
                'settlement_id' => $application->id,
                'vendor_id' => $payment?->party_id,
                'vendor_number' => $vendor?->vendor_code,
                'vendor_name' => $payment?->party_name ?: $vendor?->vendor_name,
                'source_document_type' => 'PAYMENT',
                'source_document_id' => $payment?->id,
                'source_document_number' => $payment?->payment_number,
                'source_document_date' => $payment?->payment_date,
                'target_document_type' => 'PURCHASE_INVOICE',
                'target_document_id' => $application->document_id,
                'target_document_number' => $application->document_number,
                'target_document_date' => $document?->posting_date ?? $application->applied_at,
                'original_invoice_number' => $application->document_number,
                'amount_applied' => (float) $application->amount_applied,
                'currency_code' => $application->currency?->code ?? $payment?->currency_code ?? $document?->currency_code ?? 'NGN',
                'source_remaining_before' => null,
                'source_remaining_after' => null,
                'target_remaining_before' => (float) $application->document_remaining_before,
                'target_remaining_after' => (float) $application->document_remaining_after,
                'applied_by_name' => $application->applier?->name ?? '—',
                'source_ledger_entry_id' => $sourceLedgerByPaymentId->get($payment?->id)?->id,
                'target_ledger_entry_id' => $targetLedgerByInvoiceId->get($application->document_id)?->id,
                'business_id' => $payment?->business_id ?? $document?->business_id,
                'reference_key' => null,
                'application_date' => $application->applied_at,
                'trace_status' => 'canonical',
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function creditMemoApplicationRows(array $filters): Collection
    {
        $creditEntries = VendorLedgerEntry::query()
            ->with(['source.vendor', 'currency'])
            ->where('source_type', PostedPurchaseCreditMemo::class)
            ->where('document_type', 'PURCHASE_CREDIT_MEMO')
            ->where('reversed', false)
            ->when(filled($filters['business_id'] ?? null), fn ($query, int $businessId) => $query->where('business_id', $businessId))
            ->get()
            ->filter(fn (VendorLedgerEntry $entry): bool => filled($entry->applied_to_entries))
            ->values();

        if ($creditEntries->isEmpty()) {
            return collect();
        }

        $targetEntryIds = $creditEntries
            ->flatMap(fn (VendorLedgerEntry $entry): Collection => collect($entry->applied_to_entries ?? [])
                ->pluck('entry_id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $targetEntries = VendorLedgerEntry::query()
            ->whereIn('id', $targetEntryIds)
            ->where('document_type', 'PURCHASE_INVOICE')
            ->when(filled($filters['business_id'] ?? null), fn ($query, int $businessId) => $query->where('business_id', $businessId))
            ->with(['source.vendor', 'currency'])
            ->get()
            ->keyBy('id');

        $expanded = collect();

        foreach ($creditEntries as $creditEntry) {
            /** @var PostedPurchaseCreditMemo|null $creditMemo */
            $creditMemo = $creditEntry->source instanceof PostedPurchaseCreditMemo ? $creditEntry->source : null;

            foreach (collect($creditEntry->applied_to_entries ?? [])->values() as $index => $application) {
                $targetEntry = $targetEntries->get((int) ($application['entry_id'] ?? 0));
                if (! $targetEntry) {
                    continue;
                }

                $expanded->push((object) [
                    'settlement_type' => 'CREDIT_MEMO_APPLICATION',
                    'settlement_id' => $creditEntry->id * 1000 + $index + 1,
                    'vendor_id' => $creditEntry->vendor_id,
                    'vendor_number' => $creditMemo?->vendor?->vendor_code,
                    'vendor_name' => $creditMemo?->vendor_name ?: $creditMemo?->vendor?->vendor_name ?: 'Unknown Vendor',
                    'source_document_type' => 'PURCHASE_CREDIT_MEMO',
                    'source_document_id' => $creditMemo?->id,
                    'source_document_number' => $creditMemo?->document_number ?? $creditEntry->document_number,
                    'source_document_date' => $creditMemo?->posting_date ?? $creditEntry->posting_date,
                    'target_document_type' => 'PURCHASE_INVOICE',
                    'target_document_id' => $targetEntry->source_id,
                    'target_document_number' => $targetEntry->document_number,
                    'target_document_date' => $targetEntry->posting_date,
                    'original_invoice_number' => $creditMemo?->corrects_invoice_number ?? $targetEntry->document_number,
                    'amount_applied' => (float) ($application['amount'] ?? 0),
                    'currency_code' => $creditEntry->currency_code ?? $creditMemo?->currency_code ?? $targetEntry->currency_code ?? 'NGN',
                    'source_remaining_before' => null,
                    'source_remaining_after' => null,
                    'target_remaining_before' => null,
                    'target_remaining_after' => null,
                    'applied_by_name' => $creditMemo?->poster?->name ?? $creditEntry->creator?->name ?? '—',
                    'source_ledger_entry_id' => $creditEntry->id,
                    'target_ledger_entry_id' => $targetEntry->id,
                    'business_id' => $creditEntry->business_id ?? $creditMemo?->business_id ?? $targetEntry->business_id,
                    'reference_key' => null,
                    'application_date' => Carbon::parse((string) ($application['applied_at'] ?? $creditMemo?->posted_at ?? $creditEntry->created_at)),
                    'trace_status' => 'canonical',
                ]);
            }
        }

        return $this->hydrateRemainingBalances($expanded, $creditEntries, $targetEntries);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  Collection<int, VendorLedgerEntry>  $sourceEntries
     * @param  Collection<int, VendorLedgerEntry>  $targetEntries
     * @return Collection<int, object>
     */
    private function hydrateRemainingBalances(Collection $rows, Collection $sourceEntries, Collection $targetEntries): Collection
    {
        $rows = $rows->sortBy([
            ['application_date', 'asc'],
            ['settlement_id', 'asc'],
        ])->values();

        $sourceGroups = $rows->groupBy(fn (object $row): string => (string) $row->source_ledger_entry_id);
        $targetGroups = $rows->groupBy(fn (object $row): string => (string) $row->target_ledger_entry_id);

        foreach ($sourceGroups as $sourceLedgerId => $group) {
            if (! $sourceLedgerId || ! $sourceEntries->has((int) $sourceLedgerId)) {
                continue;
            }

            $ledgerEntry = $sourceEntries->get((int) $sourceLedgerId);
            $remaining = (float) $ledgerEntry->remaining_amount + $group->sum('amount_applied');

            foreach ($group->sortBy('application_date')->values() as $row) {
                $row->source_remaining_before = round($remaining, 4);
                $remaining = round($remaining - (float) $row->amount_applied, 4);
                $row->source_remaining_after = max(0, $remaining);
            }
        }

        foreach ($targetGroups as $targetLedgerId => $group) {
            if (! $targetLedgerId || ! $targetEntries->has((int) $targetLedgerId)) {
                continue;
            }

            $ledgerEntry = $targetEntries->get((int) $targetLedgerId);
            $remaining = (float) $ledgerEntry->remaining_amount + $group->sum('amount_applied');

            foreach ($group->sortBy('application_date')->values() as $row) {
                $row->target_remaining_before = round($remaining, 4);
                $remaining = round($remaining - (float) $row->amount_applied, 4);
                $row->target_remaining_after = max(0, $remaining);
            }
        }

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        if (filled($filters['vendor_id'] ?? null)) {
            $rows = $rows->filter(fn (object $row): bool => (int) $row->vendor_id === (int) $filters['vendor_id']);
        }

        if (filled($filters['settlement_type'] ?? null)) {
            $rows = $rows->filter(fn (object $row): bool => $row->settlement_type === $filters['settlement_type']);
        }

        if (filled($filters['source_document_number'] ?? null)) {
            $needle = mb_strtolower(trim((string) $filters['source_document_number']));
            $rows = $rows->filter(fn (object $row): bool => str_contains(mb_strtolower((string) $row->source_document_number), $needle));
        }

        if (filled($filters['target_document_number'] ?? null)) {
            $needle = mb_strtolower(trim((string) $filters['target_document_number']));
            $rows = $rows->filter(fn (object $row): bool => str_contains(mb_strtolower((string) $row->target_document_number), $needle));
        }

        if (filled($filters['date_from'] ?? null)) {
            $dateFrom = (string) $filters['date_from'];
            $rows = $rows->filter(fn (object $row): bool => (string) optional($row->application_date)->toDateString() >= $dateFrom);
        }

        if (filled($filters['date_to'] ?? null)) {
            $dateTo = (string) $filters['date_to'];
            $rows = $rows->filter(fn (object $row): bool => (string) optional($row->application_date)->toDateString() <= $dateTo);
        }

        if (filled($filters['currency_code'] ?? null)) {
            $currencyCode = strtoupper((string) $filters['currency_code']);
            $rows = $rows->filter(fn (object $row): bool => strtoupper((string) $row->currency_code) === $currencyCode);
        }

        if (filled($filters['business_id'] ?? null)) {
            $businessId = (int) $filters['business_id'];
            $rows = $rows->filter(fn (object $row): bool => (int) ($row->business_id ?? 0) === $businessId);
        }

        return $rows->values();
    }

    private function rowTimestamp(object $row): int
    {
        return optional($row->application_date)?->timestamp ?? 0;
    }

    private function resolveBusinessId(?array ...$dimensions): ?int
    {
        foreach ($dimensions as $dimension) {
            if (! is_array($dimension)) {
                continue;
            }

            $value = $dimension['business_id'] ?? null;
            if (filled($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}

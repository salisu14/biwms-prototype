<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;

class CustomerSubledgerSummaryService
{
    /**
     * @param  array{customer_id?: int|null, document_type?: string|null, month_filter?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function generate(array $filters = []): array
    {
        $customerId = filled($filters['customer_id'] ?? null) ? (int) $filters['customer_id'] : null;
        $documentType = filled($filters['document_type'] ?? null) ? (string) $filters['document_type'] : null;
        $monthFilter = filled($filters['month_filter'] ?? null) ? (string) $filters['month_filter'] : null;

        $customer = $customerId !== null ? Customer::query()->find($customerId) : null;

        $entries = CustomerLedgerEntry::query()
            ->with('customer')
            ->when($customerId !== null, fn ($query) => $query->where('customer_id', $customerId))
            ->when($documentType !== null, fn ($query) => $query->where('document_type', $documentType))
            ->when($monthFilter !== null, fn ($query) => $query->whereRaw("to_char(posting_date, 'YYYY-MM') = ?", [$monthFilter]))
            ->orderByDesc('posting_date')
            ->orderByDesc('entry_number')
            ->get();

        $documentTypeSummary = $entries
            ->groupBy(fn (CustomerLedgerEntry $entry): string => (string) $entry->document_type)
            ->map(fn ($group, $type): array => [
                'type' => (string) $type,
                'count' => $group->count(),
                'debit' => (float) $group->sum('debit_amount'),
                'credit' => (float) $group->sum('credit_amount'),
                'net' => (float) $group->sum(fn (CustomerLedgerEntry $entry): float => (float) $entry->debit_amount - (float) $entry->credit_amount),
            ])
            ->sortBy('type')
            ->values()
            ->all();

        $monthBuckets = $entries
            ->groupBy(fn (CustomerLedgerEntry $entry): string => optional($entry->posting_date)?->format('Y-m') ?? 'unknown')
            ->map(fn ($group, $bucket): array => [
                'bucket' => (string) $bucket,
                'count' => $group->count(),
                'debit' => (float) $group->sum('debit_amount'),
                'credit' => (float) $group->sum('credit_amount'),
                'net' => (float) $group->sum(fn (CustomerLedgerEntry $entry): float => (float) $entry->debit_amount - (float) $entry->credit_amount),
            ])
            ->sortBy('bucket')
            ->values()
            ->all();

        $today = now()->startOfDay();
        $openEntries = $entries->filter(fn (CustomerLedgerEntry $entry): bool => (bool) $entry->open && (float) $entry->remaining_amount > 0);
        $aging = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            'over_90' => 0.0,
        ];

        foreach ($openEntries as $entry) {
            $remainingAmount = (float) $entry->remaining_amount;
            $referenceDate = $entry->due_date ?? $entry->posting_date;

            if ($referenceDate === null) {
                $aging['current'] += $remainingAmount;

                continue;
            }

            $daysPastDue = $referenceDate->startOfDay()->diffInDays($today, false);

            if ($daysPastDue <= 0) {
                $aging['current'] += $remainingAmount;
            } elseif ($daysPastDue <= 30) {
                $aging['1_30'] += $remainingAmount;
            } elseif ($daysPastDue <= 60) {
                $aging['31_60'] += $remainingAmount;
            } elseif ($daysPastDue <= 90) {
                $aging['61_90'] += $remainingAmount;
            } else {
                $aging['over_90'] += $remainingAmount;
            }
        }

        return [
            'customer' => $customer,
            'entries' => $entries,
            'documentTypeSummary' => $documentTypeSummary,
            'monthBuckets' => $monthBuckets,
            'aging' => $aging,
            'summary' => [
                'count' => $entries->count(),
                'debit' => (float) $entries->sum('debit_amount'),
                'credit' => (float) $entries->sum('credit_amount'),
                'net' => (float) $entries->sum(fn (CustomerLedgerEntry $entry): float => (float) $entry->debit_amount - (float) $entry->credit_amount),
                'open_remaining' => (float) $entries->where('open', true)->sum('remaining_amount'),
            ],
        ];
    }
}

<?php

namespace App\Services\Dashboard;

use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseReceiptLine;
use App\Models\VendorLedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate ??= now()->startOfMonth();
        $endDate ??= now();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'purchases_by_vendor' => $this->purchasesByVendor($startDate, $endDate),
            'outstanding_payables' => round($this->outstandingPayables(), 2),
            'receipts_not_invoiced' => $this->receiptsNotInvoiced(),
            'invoices_not_paid' => [
                'count' => PostedPurchaseInvoice::query()
                    ->where('cancelled', false)
                    ->where('paid_in_full', false)
                    ->count(),
                'amount' => round((float) PostedPurchaseInvoice::query()
                    ->where('cancelled', false)
                    ->where('paid_in_full', false)
                    ->sum('remaining_amount'), 2),
            ],
            'purchase_returns_credit_memos' => [
                'count' => PostedPurchaseCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->count(),
                'amount' => round((float) PostedPurchaseCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->sum('grand_total'), 2),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function purchasesByVendor(Carbon $startDate, Carbon $endDate): array
    {
        return PostedPurchaseInvoice::query()
            ->leftJoin('vendors', 'vendors.id', '=', 'posted_purchase_invoices.vendor_id')
            ->whereBetween('posted_purchase_invoices.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('posted_purchase_invoices.cancelled', false)
            ->groupBy('posted_purchase_invoices.vendor_id', 'vendors.vendor_code', 'posted_purchase_invoices.vendor_name')
            ->orderByDesc(DB::raw('SUM(posted_purchase_invoices.grand_total)'))
            ->limit(10)
            ->get([
                'posted_purchase_invoices.vendor_id',
                'vendors.vendor_code',
                'posted_purchase_invoices.vendor_name',
                DB::raw('SUM(posted_purchase_invoices.grand_total) as amount'),
            ])
            ->map(fn ($row): array => [
                'vendor_id' => $row->vendor_id !== null ? (int) $row->vendor_id : null,
                'vendor_number' => $row->vendor_code,
                'vendor_name' => (string) $row->vendor_name,
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    private function outstandingPayables(): float
    {
        return (float) VendorLedgerEntry::query()
            ->where('reversed', false)
            ->sum(DB::raw('CASE WHEN credit_amount > 0 THEN ABS(remaining_amount) ELSE -ABS(remaining_amount) END'));
    }

    /**
     * @return array{count: int, quantity: float, amount: float}
     */
    private function receiptsNotInvoiced(): array
    {
        $summary = PurchaseReceiptLine::query()
            ->whereColumn('quantity_received', '>', 'quantity_invoiced')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(quantity_received - quantity_invoiced), 0) as quantity, COALESCE(SUM((quantity_received - quantity_invoiced) * direct_unit_cost), 0) as amount')
            ->first();

        return [
            'count' => (int) ($summary->count ?? 0),
            'quantity' => round((float) ($summary->quantity ?? 0), 4),
            'amount' => round((float) ($summary->amount ?? 0), 2),
        ];
    }
}

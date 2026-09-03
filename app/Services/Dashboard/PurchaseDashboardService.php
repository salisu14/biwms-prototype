<?php

namespace App\Services\Dashboard;

use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseReceiptLine;
use App\Models\SubledgerOpeningBalance;
use App\Models\VendorLedgerEntry;
use App\Services\Business\BusinessContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseDashboardService
{
    public function __construct(private readonly BusinessContextService $businessContext) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $startDate = null, ?Carbon $endDate = null, ?int $businessId = null): array
    {
        $startDate ??= now()->startOfMonth();
        $endDate ??= now();
        $businessId = $this->businessContext->resolveId($businessId);

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'purchases_by_vendor' => $this->purchasesByVendor($startDate, $endDate, $businessId),
            'outstanding_payables' => round($this->outstandingPayables($businessId), 2),
            'receipts_not_invoiced' => $this->receiptsNotInvoiced($businessId),
            'invoices_not_paid' => [
                'count' => $this->openInvoiceLedgerQuery($businessId)->count(),
                'amount' => round((float) $this->openInvoiceLedgerQuery($businessId)
                    ->sum(DB::raw($this->remainingAmountLcyExpression('vendor_ledger_entries'))), 2),
            ],
            'purchase_returns_credit_memos' => [
                'count' => PostedPurchaseCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                    ->count(),
                'amount' => round((float) PostedPurchaseCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                    ->sum(DB::raw('grand_total * COALESCE(currency_factor, 1)')), 2),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function purchasesByVendor(Carbon $startDate, Carbon $endDate, ?int $businessId = null): array
    {
        return PostedPurchaseInvoice::query()
            ->leftJoin('vendors', 'vendors.id', '=', 'posted_purchase_invoices.vendor_id')
            ->whereBetween('posted_purchase_invoices.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($businessId !== null, fn ($query) => $query->where('posted_purchase_invoices.business_id', $businessId))
            ->where('posted_purchase_invoices.cancelled', false)
            ->groupBy('posted_purchase_invoices.vendor_id', 'vendors.vendor_code', 'posted_purchase_invoices.vendor_name')
            ->orderByDesc(DB::raw('SUM(posted_purchase_invoices.grand_total * COALESCE(posted_purchase_invoices.currency_factor, 1))'))
            ->limit(10)
            ->get([
                'posted_purchase_invoices.vendor_id',
                'vendors.vendor_code',
                'posted_purchase_invoices.vendor_name',
                DB::raw('SUM(posted_purchase_invoices.grand_total * COALESCE(posted_purchase_invoices.currency_factor, 1)) as amount'),
            ])
            ->map(fn ($row): array => [
                'vendor_id' => $row->vendor_id !== null ? (int) $row->vendor_id : null,
                'vendor_number' => $row->vendor_code,
                'vendor_name' => (string) $row->vendor_name,
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    private function outstandingPayables(?int $businessId = null): float
    {
        return (float) VendorLedgerEntry::query()
            ->where('reversed', false)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw("CASE WHEN credit_amount > 0 THEN {$this->remainingAmountLcyExpression('vendor_ledger_entries')} ELSE -({$this->remainingAmountLcyExpression('vendor_ledger_entries')}) END"));
    }

    /**
     * @return array{count: int, quantity: float, amount: float}
     */
    private function receiptsNotInvoiced(?int $businessId = null): array
    {
        $summary = PurchaseReceiptLine::query()
            ->join('purchase_receipts as pr', 'pr.id', '=', 'purchase_receipt_lines.purchase_receipt_id')
            ->whereColumn('quantity_received', '>', 'quantity_invoiced')
            ->when($businessId !== null, fn ($query) => $query->where('pr.business_id', $businessId))
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(quantity_received - quantity_invoiced), 0) as quantity, COALESCE(SUM((quantity_received - quantity_invoiced) * direct_unit_cost * COALESCE(pr.exchange_rate, 1)), 0) as amount')
            ->first();

        return [
            'count' => (int) ($summary->count ?? 0),
            'quantity' => round((float) ($summary->quantity ?? 0), 4),
            'amount' => round((float) ($summary->amount ?? 0), 2),
        ];
    }

    private function openInvoiceLedgerQuery(?int $businessId = null)
    {
        return VendorLedgerEntry::query()
            ->where('document_type', 'PURCHASE_INVOICE')
            ->where('reversed', false)
            ->where('open', true)
            ->where('credit_amount', '>', 0)
            ->whereRaw($this->remainingAmountLcyExpression('vendor_ledger_entries').' > 0')
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId));
    }

    private function remainingAmountLcyExpression(string $table): string
    {
        return "CASE WHEN {$table}.source_type = '".SubledgerOpeningBalance::class."' THEN ABS({$table}.remaining_amount) ELSE ABS({$table}.remaining_amount * COALESCE({$table}.currency_factor, 1)) END";
    }
}

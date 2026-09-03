<?php

namespace App\Services\Dashboard;

use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\SubledgerOpeningBalance;
use App\Services\Business\BusinessContextService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesDashboardService
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
            'sales_by_customer' => $this->salesByCustomer($startDate, $endDate, $businessId),
            'sales_by_item' => $this->salesByItem($startDate, $endDate, $businessId),
            'sales_by_business_posting_group' => $this->salesByBusinessPostingGroup($startDate, $endDate, $businessId),
            'outstanding_receivables' => round($this->outstandingReceivables($businessId), 2),
            'posted_invoices' => [
                'count' => PostedSalesInvoice::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->where('cancelled', false)
                    ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                    ->count(),
                'amount' => round((float) PostedSalesInvoice::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->where('cancelled', false)
                    ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                    ->sum(DB::raw('grand_total * COALESCE(currency_factor, 1)')), 2),
            ],
            'payments' => [
                'count' => $this->paymentLedgerQuery($startDate, $endDate, $businessId)->count(),
                'amount' => round((float) $this->paymentLedgerQuery($startDate, $endDate, $businessId)
                    ->sum(DB::raw('credit_amount * COALESCE(currency_factor, 1)')), 2),
            ],
            'credit_memos_returns' => [
                'count' => PostedSalesCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                    ->count(),
                'amount' => round((float) PostedSalesCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                    ->sum(DB::raw('grand_total * COALESCE(currency_factor, 1)')), 2),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function salesByCustomer(Carbon $startDate, Carbon $endDate, ?int $businessId = null): array
    {
        return PostedSalesInvoice::query()
            ->leftJoin('customers', 'customers.id', '=', 'posted_sales_invoices.customer_id')
            ->whereBetween('posted_sales_invoices.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($businessId !== null, fn ($query) => $query->where('posted_sales_invoices.business_id', $businessId))
            ->where('posted_sales_invoices.cancelled', false)
            ->groupBy('posted_sales_invoices.customer_id', 'customers.customer_number', 'posted_sales_invoices.customer_name')
            ->orderByDesc(DB::raw('SUM(posted_sales_invoices.grand_total * COALESCE(posted_sales_invoices.currency_factor, 1))'))
            ->limit(10)
            ->get([
                'posted_sales_invoices.customer_id',
                'customers.customer_number',
                'posted_sales_invoices.customer_name',
                DB::raw('SUM(posted_sales_invoices.grand_total * COALESCE(posted_sales_invoices.currency_factor, 1)) as amount'),
            ])
            ->map(fn ($row): array => [
                'customer_id' => $row->customer_id !== null ? (int) $row->customer_id : null,
                'customer_number' => $row->customer_number,
                'customer_name' => (string) $row->customer_name,
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function salesByItem(Carbon $startDate, Carbon $endDate, ?int $businessId = null): array
    {
        return PostedSalesInvoiceLine::query()
            ->join('posted_sales_invoices as psi', 'psi.id', '=', 'posted_sales_invoice_lines.posted_sales_invoice_id')
            ->whereBetween('psi.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($businessId !== null, fn ($query) => $query->where('psi.business_id', $businessId))
            ->where('psi.cancelled', false)
            ->whereNotNull('posted_sales_invoice_lines.item_id')
            ->groupBy('posted_sales_invoice_lines.item_id', 'posted_sales_invoice_lines.item_code', 'posted_sales_invoice_lines.item_description')
            ->orderByDesc(DB::raw('SUM(posted_sales_invoice_lines.line_amount * COALESCE(psi.currency_factor, 1))'))
            ->limit(10)
            ->get([
                'posted_sales_invoice_lines.item_id',
                'posted_sales_invoice_lines.item_code',
                'posted_sales_invoice_lines.item_description',
                DB::raw('SUM(posted_sales_invoice_lines.quantity_base) as quantity_base'),
                DB::raw('SUM(posted_sales_invoice_lines.line_amount * COALESCE(psi.currency_factor, 1)) as amount'),
            ])
            ->map(fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'item_code' => (string) $row->item_code,
                'item_description' => (string) $row->item_description,
                'quantity_base' => round((float) $row->quantity_base, 4),
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function salesByBusinessPostingGroup(Carbon $startDate, Carbon $endDate, ?int $businessId = null): array
    {
        return PostedSalesInvoice::query()
            ->leftJoin('general_business_posting_groups as gbpg', 'gbpg.id', '=', 'posted_sales_invoices.general_business_posting_group_id')
            ->whereBetween('posted_sales_invoices.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($businessId !== null, fn ($query) => $query->where('posted_sales_invoices.business_id', $businessId))
            ->where('posted_sales_invoices.cancelled', false)
            ->groupBy('posted_sales_invoices.general_business_posting_group_id', 'gbpg.code', 'gbpg.description')
            ->orderByDesc(DB::raw('SUM(posted_sales_invoices.grand_total * COALESCE(posted_sales_invoices.currency_factor, 1))'))
            ->get([
                'posted_sales_invoices.general_business_posting_group_id',
                'gbpg.code',
                'gbpg.description',
                DB::raw('SUM(posted_sales_invoices.grand_total * COALESCE(posted_sales_invoices.currency_factor, 1)) as amount'),
            ])
            ->map(fn ($row): array => [
                'general_business_posting_group_id' => $row->general_business_posting_group_id !== null ? (int) $row->general_business_posting_group_id : null,
                'code' => $row->code,
                'description' => $row->description ?? 'Unassigned',
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    private function outstandingReceivables(?int $businessId = null): float
    {
        $remainingLcy = $this->remainingAmountLcyExpression('customer_ledger_entries');

        return (float) CustomerLedgerEntry::query()
            ->where('reversed', false)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw("CASE WHEN debit_amount > 0 THEN {$remainingLcy} ELSE -({$remainingLcy}) END"));
    }

    private function paymentLedgerQuery(Carbon $startDate, Carbon $endDate, ?int $businessId = null)
    {
        return CustomerLedgerEntry::query()
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('reversed', false)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->where('credit_amount', '>', 0)
            ->whereIn('document_type', ['PAYMENT', 'CASH_RECEIPT', 'BANK_TRANSFER']);
    }

    private function remainingAmountLcyExpression(string $table): string
    {
        return "CASE WHEN {$table}.source_type = '".SubledgerOpeningBalance::class."' THEN ABS({$table}.remaining_amount) ELSE ABS({$table}.remaining_amount * COALESCE({$table}.currency_factor, 1)) END";
    }
}

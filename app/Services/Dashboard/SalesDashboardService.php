<?php

namespace App\Services\Dashboard;

use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesDashboardService
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
            'sales_by_customer' => $this->salesByCustomer($startDate, $endDate),
            'sales_by_item' => $this->salesByItem($startDate, $endDate),
            'sales_by_business_posting_group' => $this->salesByBusinessPostingGroup($startDate, $endDate),
            'outstanding_receivables' => round($this->outstandingReceivables(), 2),
            'posted_invoices' => [
                'count' => PostedSalesInvoice::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->where('cancelled', false)
                    ->count(),
                'amount' => round((float) PostedSalesInvoice::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->where('cancelled', false)
                    ->sum('grand_total'), 2),
            ],
            'payments' => [
                'count' => $this->paymentLedgerQuery($startDate, $endDate)->count(),
                'amount' => round((float) $this->paymentLedgerQuery($startDate, $endDate)->sum('credit_amount'), 2),
            ],
            'credit_memos_returns' => [
                'count' => PostedSalesCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->count(),
                'amount' => round((float) PostedSalesCreditMemo::query()
                    ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->sum('grand_total'), 2),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function salesByCustomer(Carbon $startDate, Carbon $endDate): array
    {
        return PostedSalesInvoice::query()
            ->leftJoin('customers', 'customers.id', '=', 'posted_sales_invoices.customer_id')
            ->whereBetween('posted_sales_invoices.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('posted_sales_invoices.cancelled', false)
            ->groupBy('posted_sales_invoices.customer_id', 'customers.customer_number', 'posted_sales_invoices.customer_name')
            ->orderByDesc(DB::raw('SUM(posted_sales_invoices.grand_total)'))
            ->limit(10)
            ->get([
                'posted_sales_invoices.customer_id',
                'customers.customer_number',
                'posted_sales_invoices.customer_name',
                DB::raw('SUM(posted_sales_invoices.grand_total) as amount'),
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
    private function salesByItem(Carbon $startDate, Carbon $endDate): array
    {
        return PostedSalesInvoiceLine::query()
            ->join('posted_sales_invoices as psi', 'psi.id', '=', 'posted_sales_invoice_lines.posted_sales_invoice_id')
            ->whereBetween('psi.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('psi.cancelled', false)
            ->whereNotNull('posted_sales_invoice_lines.item_id')
            ->groupBy('posted_sales_invoice_lines.item_id', 'posted_sales_invoice_lines.item_code', 'posted_sales_invoice_lines.item_description')
            ->orderByDesc(DB::raw('SUM(posted_sales_invoice_lines.line_amount)'))
            ->limit(10)
            ->get([
                'posted_sales_invoice_lines.item_id',
                'posted_sales_invoice_lines.item_code',
                'posted_sales_invoice_lines.item_description',
                DB::raw('SUM(posted_sales_invoice_lines.quantity_base) as quantity_base'),
                DB::raw('SUM(posted_sales_invoice_lines.line_amount) as amount'),
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
    private function salesByBusinessPostingGroup(Carbon $startDate, Carbon $endDate): array
    {
        return PostedSalesInvoice::query()
            ->leftJoin('general_business_posting_groups as gbpg', 'gbpg.id', '=', 'posted_sales_invoices.general_business_posting_group_id')
            ->whereBetween('posted_sales_invoices.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('posted_sales_invoices.cancelled', false)
            ->groupBy('posted_sales_invoices.general_business_posting_group_id', 'gbpg.code', 'gbpg.description')
            ->orderByDesc(DB::raw('SUM(posted_sales_invoices.grand_total)'))
            ->get([
                'posted_sales_invoices.general_business_posting_group_id',
                'gbpg.code',
                'gbpg.description',
                DB::raw('SUM(posted_sales_invoices.grand_total) as amount'),
            ])
            ->map(fn ($row): array => [
                'general_business_posting_group_id' => $row->general_business_posting_group_id !== null ? (int) $row->general_business_posting_group_id : null,
                'code' => $row->code,
                'description' => $row->description ?? 'Unassigned',
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    private function outstandingReceivables(): float
    {
        return (float) CustomerLedgerEntry::query()
            ->where('reversed', false)
            ->sum(DB::raw('CASE WHEN debit_amount > 0 THEN ABS(remaining_amount) ELSE -ABS(remaining_amount) END'));
    }

    private function paymentLedgerQuery(Carbon $startDate, Carbon $endDate)
    {
        return CustomerLedgerEntry::query()
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('reversed', false)
            ->where('credit_amount', '>', 0)
            ->whereIn('document_type', ['PAYMENT', 'CASH_RECEIPT', 'BANK_TRANSFER']);
    }
}

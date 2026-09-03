<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\PostedSalesInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerSettlementHistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function rows(array $filters = []): Collection
    {
        return $this->query($filters)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        $paymentApplications = $this->paymentApplicationQuery($filters);
        $creditMemoApplications = $this->creditMemoApplicationQuery($filters);

        return DB::query()
            ->fromSub($paymentApplications->unionAll($creditMemoApplications), 'settlements')
            ->orderByDesc('application_date')
            ->orderByDesc('settlement_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paymentApplicationQuery(array $filters): Builder
    {
        $query = DB::table('payment_applications as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->leftJoin('customers as c', function ($join): void {
                $join->on('c.id', '=', 'p.party_id')
                    ->where('p.party_type', '=', 'CUSTOMER');
            })
            ->leftJoin('posted_sales_invoices as psi', function ($join): void {
                $join->on('psi.id', '=', 'pa.document_id')
                    ->where('pa.document_type', '=', 'SALES_INVOICE');
            })
            ->leftJoin('customer_ledger_entries as source_cle', function ($join): void {
                $join->on('source_cle.source_id', '=', 'p.id')
                    ->where('source_cle.source_type', '=', Payment::class)
                    ->where('source_cle.document_type', '=', 'PAYMENT');
            })
            ->leftJoin('customer_ledger_entries as target_cle', function ($join): void {
                $join->on('target_cle.source_id', '=', 'pa.document_id')
                    ->where('target_cle.source_type', '=', PostedSalesInvoice::class)
                    ->where('target_cle.document_type', '=', 'SALES_INVOICE');
            })
            ->leftJoin('users as u', 'u.id', '=', 'pa.applied_by')
            ->where('p.party_type', 'CUSTOMER')
            ->where('pa.document_type', 'SALES_INVOICE')
            ->where('pa.reversed', false)
            ->selectRaw("
                'PAYMENT_APPLICATION'::text as settlement_type,
                pa.id as settlement_id,
                c.id as customer_id,
                c.customer_number as customer_number,
                c.name as customer_name,
                'PAYMENT'::text as source_document_type,
                p.id as source_document_id,
                p.payment_number as source_document_number,
                p.payment_date as source_document_date,
                'SALES_INVOICE'::text as target_document_type,
                psi.id as target_document_id,
                pa.document_number as target_document_number,
                psi.posting_date as target_document_date,
                pa.amount_applied as amount_applied,
                NULL::numeric as source_remaining_before,
                NULL::numeric as source_remaining_after,
                pa.document_remaining_before as target_remaining_before,
                pa.document_remaining_after as target_remaining_after,
                COALESCE(p.currency_code, psi.currency_code) as currency_code,
                pa.applied_at as application_date,
                pa.applied_by as applied_by_id,
                u.name as applied_by_name,
                NULL::text as reference_key,
                source_cle.id as source_ledger_entry_id,
                target_cle.id as target_ledger_entry_id,
                COALESCE(p.business_id, psi.business_id) as business_id,
                psi.document_number as original_invoice_number,
                'canonical'::text as trace_status
            ");

        return $this->applyCommonFilters($query, $filters, 'PAYMENT_APPLICATION', [
            'customer_id' => 'c.id',
            'source_document_number' => 'p.payment_number',
            'target_document_number' => 'pa.document_number',
            'application_date' => 'pa.applied_at',
            'currency_code' => 'COALESCE(p.currency_code, psi.currency_code)',
            'business_id' => 'COALESCE(p.business_id, psi.business_id)',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function creditMemoApplicationQuery(array $filters): Builder
    {
        $query = DB::table('customer_ledger_applications as cla')
            ->join('posted_sales_credit_memos as pscm', 'pscm.id', '=', 'cla.source_posted_sales_credit_memo_id')
            ->join('posted_sales_invoices as psi', 'psi.id', '=', 'cla.target_posted_sales_invoice_id')
            ->leftJoin('customers as c', 'c.id', '=', 'cla.customer_id')
            ->leftJoin('customer_ledger_entries as source_cle', 'source_cle.id', '=', 'cla.source_customer_ledger_entry_id')
            ->leftJoin('customer_ledger_entries as target_cle', 'target_cle.id', '=', 'cla.target_customer_ledger_entry_id')
            ->leftJoin('posted_sales_invoices as original_psi', 'original_psi.id', '=', 'pscm.corrected_invoice_id')
            ->leftJoin('users as u', 'u.id', '=', 'cla.applied_by')
            ->where('cla.reversed', false)
            ->selectRaw("
                'CREDIT_MEMO_APPLICATION'::text as settlement_type,
                cla.id as settlement_id,
                c.id as customer_id,
                c.customer_number as customer_number,
                c.name as customer_name,
                'SALES_CREDIT_MEMO'::text as source_document_type,
                pscm.id as source_document_id,
                pscm.document_number as source_document_number,
                pscm.posting_date as source_document_date,
                'SALES_INVOICE'::text as target_document_type,
                psi.id as target_document_id,
                psi.document_number as target_document_number,
                psi.posting_date as target_document_date,
                cla.amount as amount_applied,
                cla.source_remaining_before as source_remaining_before,
                cla.source_remaining_after as source_remaining_after,
                cla.target_remaining_before as target_remaining_before,
                cla.target_remaining_after as target_remaining_after,
                cla.currency_code as currency_code,
                cla.applied_at as application_date,
                cla.applied_by as applied_by_id,
                u.name as applied_by_name,
                cla.idempotency_key as reference_key,
                cla.source_customer_ledger_entry_id as source_ledger_entry_id,
                cla.target_customer_ledger_entry_id as target_ledger_entry_id,
                COALESCE(pscm.business_id, psi.business_id) as business_id,
                COALESCE(original_psi.document_number, pscm.corrected_invoice_number) as original_invoice_number,
                'canonical'::text as trace_status
            ");

        return $this->applyCommonFilters($query, $filters, 'CREDIT_MEMO_APPLICATION', [
            'customer_id' => 'cla.customer_id',
            'source_document_number' => 'pscm.document_number',
            'target_document_number' => 'psi.document_number',
            'application_date' => 'cla.applied_at',
            'currency_code' => 'cla.currency_code',
            'business_id' => 'COALESCE(pscm.business_id, psi.business_id)',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $columns
     */
    private function applyCommonFilters(Builder $query, array $filters, string $settlementType, array $columns): Builder
    {
        if (filled($filters['settlement_type'] ?? null) && $filters['settlement_type'] !== $settlementType) {
            return $query->whereRaw('1 = 0');
        }

        if (filled($filters['customer_id'] ?? null)) {
            $query->where($columns['customer_id'], (int) $filters['customer_id']);
        }

        if (filled($filters['source_document_number'] ?? null)) {
            $query->where($columns['source_document_number'], 'ilike', '%'.trim((string) $filters['source_document_number']).'%');
        }

        if (filled($filters['target_document_number'] ?? null)) {
            $query->where($columns['target_document_number'], 'ilike', '%'.trim((string) $filters['target_document_number']).'%');
        }

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate($columns['application_date'], '>=', (string) $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate($columns['application_date'], '<=', (string) $filters['date_to']);
        }

        if (filled($filters['currency_code'] ?? null)) {
            $query->whereRaw($columns['currency_code'].' = ?', [(string) $filters['currency_code']]);
        }

        if (filled($filters['business_id'] ?? null)) {
            $query->whereRaw($columns['business_id'].' = ?', [(int) $filters['business_id']]);
        }

        return $query;
    }
}

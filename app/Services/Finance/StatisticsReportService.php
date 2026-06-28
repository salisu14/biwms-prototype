<?php

namespace App\Services\Finance;

use App\Models\GlEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsReportService
{
    /**
     * @param  array{date_from?: mixed, date_to?: mixed, gen_bus_posting_group_id?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function sales(array $filters = []): array
    {
        return $this->generate(
            type: 'sales',
            title: 'Sales Statistics',
            amountLabel: 'Revenue',
            amountColumn: 'credit_amount',
            filters: $filters,
        );
    }

    /**
     * @param  array{date_from?: mixed, date_to?: mixed, gen_bus_posting_group_id?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function purchases(array $filters = []): array
    {
        return $this->generate(
            type: 'purchase',
            title: 'Purchase Statistics',
            amountLabel: 'Cost',
            amountColumn: 'debit_amount',
            filters: $filters,
        );
    }

    /**
     * @param  array{date_from?: mixed, date_to?: mixed, gen_bus_posting_group_id?: mixed}  $filters
     * @return array{date_from: string, date_to: string, gen_bus_posting_group_id: int|null, posting_group: string|null}
     */
    public function normalizeFilters(array $filters = []): array
    {
        $from = filled($filters['date_from'] ?? null)
            ? Carbon::parse((string) $filters['date_from'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $to = filled($filters['date_to'] ?? null)
            ? Carbon::parse((string) $filters['date_to'])->toDateString()
            : now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $postingGroupId = filled($filters['gen_bus_posting_group_id'] ?? null)
            ? (int) $filters['gen_bus_posting_group_id']
            : null;

        return [
            'date_from' => $from,
            'date_to' => $to,
            'gen_bus_posting_group_id' => $postingGroupId,
            'posting_group' => $postingGroupId !== null
                ? DB::table('general_business_posting_groups')->where('id', $postingGroupId)->value('description')
                : null,
        ];
    }

    /**
     * @param  array{date_from?: mixed, date_to?: mixed, gen_bus_posting_group_id?: mixed}  $filters
     * @return array<string, mixed>
     */
    private function generate(string $type, string $title, string $amountLabel, string $amountColumn, array $filters): array
    {
        $period = $this->normalizeFilters($filters);

        $postingGroupIdSql = 'COALESCE(gl_entries.general_business_posting_group_id, coa.gen_bus_posting_group_id)';

        $query = GlEntry::query()
            ->join('chart_of_accounts as coa', 'gl_entries.chart_of_account_id', '=', 'coa.id')
            ->leftJoin('general_business_posting_groups as gbpg', function ($join) use ($postingGroupIdSql): void {
                $join->on('gbpg.id', '=', DB::raw($postingGroupIdSql));
            })
            ->whereBetween('gl_entries.posting_date', [$period['date_from'], $period['date_to']])
            ->where("gl_entries.{$amountColumn}", '>', 0)
            ->where('coa.structural_type', 'posting')
            ->select(
                'gbpg.id as gen_bus_posting_group_id',
                'gbpg.code as group_code',
                'gbpg.description as group_name',
                DB::raw("COALESCE(SUM(gl_entries.{$amountColumn}), 0) as amount"),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw("COALESCE(AVG(gl_entries.{$amountColumn}), 0) as average_amount"),
                DB::raw("COALESCE(MAX(gl_entries.{$amountColumn}), 0) as max_amount"),
                DB::raw("COALESCE(MIN(gl_entries.{$amountColumn}), 0) as min_amount"),
                DB::raw('COUNT(DISTINCT gl_entries.chart_of_account_id) as accounts_used'),
            )
            ->groupBy('gbpg.id', 'gbpg.code', 'gbpg.description')
            ->orderByDesc('amount');

        if ($period['gen_bus_posting_group_id'] !== null) {
            $query->whereRaw("{$postingGroupIdSql} = ?", [$period['gen_bus_posting_group_id']]);
        }

        $results = $query->get();
        $totalAmount = (float) $results->sum('amount');

        $rows = $results
            ->map(fn ($row): array => [
                'group_id' => $row->gen_bus_posting_group_id !== null ? (int) $row->gen_bus_posting_group_id : null,
                'group_code' => (string) ($row->group_code ?? 'N/A'),
                'group_name' => (string) ($row->group_name ?? 'Unassigned'),
                'amount' => round((float) $row->amount, 2),
                'transaction_count' => (int) $row->transaction_count,
                'average_amount' => round((float) $row->average_amount, 2),
                'max_amount' => round((float) $row->max_amount, 2),
                'min_amount' => round((float) $row->min_amount, 2),
                'accounts_used' => (int) $row->accounts_used,
                'percentage_of_total' => $totalAmount > 0
                    ? round(((float) $row->amount / $totalAmount) * 100, 2)
                    : 0.0,
            ])
            ->values()
            ->all();

        $totalTransactions = (int) collect($rows)->sum('transaction_count');

        return [
            'type' => $type,
            'title' => $title,
            'amount_label' => $amountLabel,
            'period' => $period,
            'summary' => [
                'total_amount' => round($totalAmount, 2),
                'total_transactions' => $totalTransactions,
                'average_amount' => $totalTransactions > 0 ? round($totalAmount / $totalTransactions, 2) : 0.0,
                'groups_count' => count($rows),
            ],
            'rows' => $rows,
        ];
    }
}

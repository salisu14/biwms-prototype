<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GlAccountBalanceService
{
    /**
     * Recalculate one cached account balance from the G/L source of truth.
     */
    public function syncAccount(int|string|null $accountId): void
    {
        if (! $accountId) {
            return;
        }

        DB::transaction(function () use ($accountId): void {
            $account = ChartOfAccount::query()
                ->whereKey($accountId)
                ->lockForUpdate()
                ->first();

            if (! $account) {
                return;
            }

            ChartOfAccount::query()
                ->whereKey($account->id)
                ->update(['balance' => $this->calculateAccountBalance($account->id)]);
        });
    }

    /**
     * Recalculate every cached account balance from the G/L source of truth.
     */
    public function syncAll(): int
    {
        $count = 0;

        ChartOfAccount::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $accounts) use (&$count): void {
                foreach ($accounts as $account) {
                    $this->syncAccount($account->id);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Return the canonical ledger balance for one account.
     */
    public function calculateAccountBalance(int|string $accountId): string
    {
        $balance = GlEntry::query()
            ->where('chart_of_account_id', $accountId)
            ->selectRaw('COALESCE(SUM(amount), 0) as balance')
            ->value('balance');

        return number_format((float) ($balance ?? 0), 2, '.', '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function balanceMismatches(): array
    {
        $ledgerBalances = GlEntry::query()
            ->selectRaw('chart_of_account_id, COALESCE(SUM(amount), 0) as ledger_balance')
            ->groupBy('chart_of_account_id');

        return ChartOfAccount::query()
            ->leftJoinSub($ledgerBalances, 'ledger_balances', function ($join): void {
                $join->on('chart_of_accounts.id', '=', 'ledger_balances.chart_of_account_id');
            })
            ->whereRaw('ABS(COALESCE(chart_of_accounts.balance, 0) - COALESCE(ledger_balances.ledger_balance, 0)) > 0.01')
            ->orderBy('chart_of_accounts.account_number')
            ->limit(500)
            ->get([
                'chart_of_accounts.id',
                'chart_of_accounts.account_number',
                'chart_of_accounts.name',
                'chart_of_accounts.balance as cached_balance',
                DB::raw('COALESCE(ledger_balances.ledger_balance, 0) as ledger_balance'),
                DB::raw('(COALESCE(chart_of_accounts.balance, 0) - COALESCE(ledger_balances.ledger_balance, 0)) as difference'),
            ])
            ->map(fn ($account): array => [
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'account_name' => $account->name,
                'cached_balance' => (float) $account->cached_balance,
                'ledger_balance' => (float) $account->ledger_balance,
                'difference' => (float) $account->difference,
                'severity' => 'warning',
                'suggested_remediation' => 'Run php artisan accounting:sync-balances after reviewing that no posting transaction is still in progress.',
            ])
            ->all();
    }
}

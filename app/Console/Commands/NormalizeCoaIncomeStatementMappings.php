<?php

namespace App\Console\Commands;

use App\Enums\IncomeBalanceType;
use App\Models\ChartOfAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:normalize-coa-income-statement-mappings {--dry-run : Show changes without saving}')]
#[Description('Normalize Chart of Accounts account_type and income_balance to BC-style range mapping')]
class NormalizeCoaIncomeStatementMappings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        ChartOfAccount::query()
            ->orderBy('account_number')
            ->chunkById(200, function ($accounts) use (&$updated, $dryRun): void {
                foreach ($accounts as $account) {
                    $accountNo = preg_replace('/\D/', '', (string) $account->account_number);
                    if (! $accountNo) {
                        continue;
                    }

                    $prefix = (int) substr($accountNo, 0, 1);
                    [$targetType, $targetIncomeBalance] = match ($prefix) {
                        1 => ['ASSET', IncomeBalanceType::BALANCE_SHEET],
                        2 => ['LIABILITY', IncomeBalanceType::BALANCE_SHEET],
                        3 => ['EQUITY', IncomeBalanceType::BALANCE_SHEET],
                        4 => ['REVENUE', IncomeBalanceType::INCOME_STATEMENT],
                        5 => ['COGS', IncomeBalanceType::INCOME_STATEMENT],
                        6 => ['EXPENSE', IncomeBalanceType::INCOME_STATEMENT],
                        7 => ['INTEREST', IncomeBalanceType::INCOME_STATEMENT],
                        8 => ['TAX', IncomeBalanceType::INCOME_STATEMENT],
                        default => [null, null],
                    };

                    if (! $targetType || ! $targetIncomeBalance) {
                        continue;
                    }

                    $currentType = strtoupper((string) $account->account_type);
                    $currentIncomeBalance = $account->income_balance instanceof IncomeBalanceType
                        ? $account->income_balance->value
                        : (int) $account->income_balance;

                    $targetIncomeBalanceValue = $targetIncomeBalance->value;

                    if ($currentType === $targetType && $currentIncomeBalance === $targetIncomeBalanceValue) {
                        continue;
                    }

                    $this->line(sprintf(
                        '%s %s: type %s -> %s | income_balance %s -> %s',
                        $account->account_number,
                        $account->name,
                        $currentType ?: 'NULL',
                        $targetType,
                        (string) $currentIncomeBalance,
                        (string) $targetIncomeBalanceValue
                    ));

                    $updated++;

                    if (! $dryRun) {
                        $account->forceFill([
                            'account_type' => $targetType,
                            'income_balance' => $targetIncomeBalance,
                        ])->save();
                    }
                }
            });

        $this->info($dryRun
            ? "Dry run completed. {$updated} account(s) require normalization."
            : "Normalization completed. {$updated} account(s) updated.");

        return self::SUCCESS;
    }
}

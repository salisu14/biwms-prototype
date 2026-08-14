<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\GlAccountBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('biwms:gl-balance-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--export= : Write the JSON report to a file path}')]
#[Description('Report cached G/L account balance mismatches without mutating data.')]
class BiwmsGlBalanceReconcile extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GlAccountBalanceService $balanceService): int
    {
        $findings = $balanceService->balanceMismatches();
        $report = [
            'summary' => [
                'mode' => 'report-only',
                'mismatch_count' => count($findings),
            ],
            'findings' => $findings,
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS G/L Account Balance Reconciliation');
        $this->line('Mode: report-only. No chart of account balances were changed.');

        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }

        $this->line('Mismatches: '.$report['summary']['mismatch_count']);

        if ($this->option('details') && $findings !== []) {
            $this->newLine();

            foreach ($findings as $finding) {
                $this->line(sprintf(
                    '[%s] account=%s %s cached=%s ledger=%s difference=%s',
                    $finding['severity'],
                    $finding['account_number'],
                    $finding['account_name'],
                    number_format($finding['cached_balance'], 2, '.', ''),
                    number_format($finding['ledger_balance'], 2, '.', ''),
                    number_format($finding['difference'], 2, '.', ''),
                ));
                $this->line('  remediation: '.$finding['suggested_remediation']);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportReport(array $report, string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

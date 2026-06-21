<?php

namespace App\Console\Commands;

use App\Enums\CalculationMethod;
use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('payroll:generate {--month=} {--year=}')]
#[Description('Generate a draft payroll document for all active employees based on active compensation rules.')]
class GeneratePayroll extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = $this->option('month') ?? date('m');
        $year = $this->option('year') ?? date('Y');

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $docNo = 'PRL-'.$start->format('Ym').'-'.rand(100, 999);

        $this->info("Generating payroll draft {$docNo} for {$start->format('F Y')}...");

        DB::transaction(function () use ($docNo, $start, $end) {
            $doc = PayrollDocument::create([
                'document_number' => $docNo,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'status' => PayrollStatus::OPEN,
                'remarks' => 'Auto-generated batch',
            ]);

            // Use PayrollCalculationService to compute lines accurately
            app(\App\Services\PayrollCalculationService::class)->calculate($doc);
        });

        $this->info("Payroll document {$docNo} created successfully as OPEN.");
    }
}

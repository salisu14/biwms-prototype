<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Enums\RecurringMethod;
use App\Models\GeneralJournalLine;
use App\Models\GlEntry;
use App\Models\RecurringJournalLine;
use App\Models\RecurringJournalTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class RecurringJournalPostingRoutine extends AbstractJournalPostingRoutine
{
    public function processRecurringEntries(Carbon $postingDate): void
    {
        $templates = RecurringJournalTemplate::where('is_active', true)
            ->where('next_posting_date', '<=', $postingDate)
            ->get();

        foreach ($templates as $template) {
            foreach ($template->batches as $batch) {
                $this->processBatch($batch, $postingDate);
            }

            $template->update([
                'last_posting_date' => $postingDate,
                'next_posting_date' => $this->calculateNextDate($template, $postingDate),
            ]);
        }
    }

    /**
     * @param  RecurringJournalLine  $line
     */
    protected function validateLine(object $line): void
    {
        if ($line->line_status !== 'active') {
            return; // Skip inactive lines
        }

        if ($line->expiration_date && $line->expiration_date < now()) {
            $this->errors[] = "Line {$line->line_no}: Has expired";
        }
    }

    /**
     * @param  RecurringJournalLine  $line
     */
    protected function postLine(object $line): void
    {
        $amount = $this->calculateAmount($line);

        // Create General Journal Line in target batch
        $targetBatch = $this->getOrCreateTargetBatch($line);

        $newLine = GeneralJournalLine::create([
            'batch_id' => $targetBatch->id,
            'posting_date' => now(),
            'account_id' => $line->account_id,
            'description' => $line->description,
            'debit_amount' => $amount > 0 ? $amount : 0,
            'credit_amount' => $amount < 0 ? abs($amount) : 0,
            'amount_lcy' => $amount,
            'shortcut_dimension_1_code' => $line->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $line->shortcut_dimension_2_code,
            'dimension_set_entry' => $line->dimension_set_entry,
            'source_code' => $line->source_code,
            'reason_code' => $line->reason_code,
            'created_by' => Auth::id(),
        ]);

        // Handle reversing entries
        if ($line->recurring_method->isReversing()) {
            $reversalDate = $this->calculateReversalDate($line);

            GeneralJournalLine::create([
                'batch_id' => $targetBatch->id,
                'posting_date' => $reversalDate,
                'account_id' => $line->account_id,
                'description' => $line->description.' (Reversal)',
                'debit_amount' => $amount < 0 ? abs($amount) : 0,
                'credit_amount' => $amount > 0 ? $amount : 0,
                'amount_lcy' => -$amount,
                'created_by' => Auth::id(),
            ]);
        }

        // Update recurring line tracking
        $line->update([
            'last_posting_date' => now(),
            'next_posting_date' => $this->calculateNextLineDate($line),
            'posting_count' => $line->posting_count + 1,
        ]);

        $this->postedEntries[] = $newLine;
    }

    private function calculateAmount(RecurringJournalLine $line): float
    {
        return match ($line->recurring_method) {
            RecurringMethod::FIXED, RecurringMethod::REVERSING_FIXED => (float) $line->amount,
            RecurringMethod::VARIABLE, RecurringMethod::REVERSING_VARIABLE => $this->evaluateFormula($line->calculation_formula),
            RecurringMethod::BALANCE, RecurringMethod::REVERSING_BALANCE => $this->calculateBalanceAmount($line),
        };
    }

    private function evaluateFormula(?string $formula): float
    {
        if (! $formula) {
            return 0;
        }

        // Implement formula parser (e.g., "GL(1000).Balance * 0.05")
        return 0;
    }

    private function calculateBalanceAmount(RecurringJournalLine $line): float
    {
        if (! $line->account_to_calculate_balance) {
            return 0;
        }

        $balance = GlEntry::where('account_code', $line->account_to_calculate_balance)
            ->sum(\DB::raw('debit_amount - credit_amount'));

        return $balance * ($line->percentage_for_balance / 100);
    }

    private function calculateNextDate($template, Carbon $current): Carbon
    {
        return match ($template->recurring_frequency) {
            'daily' => $current->copy()->addDays($template->recurring_interval),
            'weekly' => $current->copy()->addWeeks($template->recurring_interval),
            'monthly' => $current->copy()->addMonths($template->recurring_interval),
            'quarterly' => $current->copy()->addQuarters($template->recurring_interval),
            'yearly' => $current->copy()->addYears($template->recurring_interval),
        };
    }
}

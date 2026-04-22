<?php

namespace App\Services\Journal;

use App\Models\GlEntry;
use App\Models\JournalBatch;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class JournalPostingService
{
    /**
     * Post any journal batch - routes to correct handler
     */
    public function postBatch(JournalBatch $batch)
    {
        $templateType = $batch->template->type;

        return match($templateType) {
            'General' => $this->postGeneralJournal($batch),
            'Item' => $this->postItemJournal($batch),
            'FixedAsset' => $this->postFAJournal($batch),
            'Resource' => $this->postResourceJournal($batch),
            'Warehouse' => $this->postWarehouseJournal($batch),
            'CashReceipt' => $this->postCashReceiptJournal($batch),
            'Payment' => $this->postPaymentJournal($batch),
            'Job' => $this->postJobJournal($batch),
            default => throw new \Exception("Unsupported journal type: {$templateType}"),
        };
    }

    /**
     * @throws \Throwable
     */
    protected function postGeneralJournal(JournalBatch $batch)
    {
        return DB::transaction(function() use ($batch) {
            foreach ($batch->lines as $line) {
                $line->validate();

                // Create G/L entries
                $this->createGLEntry($line);

                // Handle recurring
                if ($batch->recurring) {
                    $generalLine = $line->generalJournalLine;
                    if ($generalLine && $generalLine->entry_type === 'Recurring') {
                        $generalLine->generateNextRecurring();
                    }
                }

                $line->update(['status' => 'Posted', 'posted_at' => now()]);
            }

            // Copy to posted journal lines if configured
            if ($batch->template->copy_to_posted_jnl_lines) {
                $this->copyToPostedLines($batch);
            }
        });
    }

    protected function createGLEntry(JournalLine $line)
    {
        // Debit entry
        if ($line->debit_amount > 0) {
            GLEntry::create([
                'account_no' => $line->account_no,
                'debit_amount' => $line->debit_amount,
                'credit_amount' => 0,
                'posting_date' => $line->posting_date,
                'document_no' => $line->document_no,
                'description' => $line->description,
                'source_code' => $line->source_code,
                'dimensions' => $line->dimensions,
            ]);
        }

        // Credit entry
        if ($line->credit_amount > 0) {
            GLEntry::create([
                'account_no' => $line->bal_account_no ?? $line->account_no,
                'debit_amount' => 0,
                'credit_amount' => $line->credit_amount,
                'posting_date' => $line->posting_date,
                'document_no' => $line->document_no,
                'description' => $line->description,
                'source_code' => $line->source_code,
                'dimensions' => $line->dimensions,
            ]);
        }
    }

    protected function verifyBalance($batch)
    {
        $totalDebits = $batch->lines->sum('debit_amount');
        $totalCredits = $batch->lines->sum('credit_amount');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw new \Exception("Journal batch is not balanced: Debits={$totalDebits}, Credits={$totalCredits}");
        }
    }
}

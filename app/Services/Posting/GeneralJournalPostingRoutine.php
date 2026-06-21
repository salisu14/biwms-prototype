<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Models\GeneralJournalLine;
use App\Models\GlEntry;

class GeneralJournalPostingRoutine extends AbstractJournalPostingRoutine
{
    /**
     * @param  GeneralJournalLine  $line
     */
    protected function validateLine(object $line): void
    {
        $errors = $line->validate();
        foreach ($errors as $error) {
            $this->errors[] = "Line {$line->line_no}: {$error}";
        }

        // Template-specific validations
        $template = $line->batch->template;

        if ($template->check_amount_sign && $line->getNetAmount() < 0) {
            // Verify account allows negative entries
        }

        // Check account type restrictions
        if ($template->allowed_account_types && ! in_array($line->account->account_type, $template->allowed_account_types)) {
            $this->errors[] = "Line {$line->line_no}: Account type not allowed in this template";
        }
    }

    /**
     * @param  GeneralJournalLine  $line
     */
    protected function postLine(object $line): void
    {
        $template = $line->batch->template;
        $documentNo = $template->postingNumberSeries?->getNextNo() ?? $line->document_no;

        // Create GL Entry for main account
        $glEntry = $this->createGeneralLedgerEntry([
            'account_id' => $line->account_id,
            'posting_date' => $line->posting_date,
            'document_type' => $line->document_type,
            'document_no' => $documentNo,
            'external_document_no' => $line->external_document_no,
            'description' => $line->description,
            'debit_amount' => $line->debit_amount,
            'credit_amount' => $line->credit_amount,
            'amount_lcy' => $line->amount_lcy,
            'currency_code' => $line->currency_code,
            'currency_factor' => $line->currency_factor,
            'amount_currency' => $line->amount_currency,
            'shortcut_dimension_1_code' => $line->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $line->shortcut_dimension_2_code,
            'dimension_set_entry' => $line->dimension_set_entry,
            'source_code' => $line->source_code ?? $template->source_code,
            'reason_code' => $line->reason_code,
            'business_unit_id' => $line->business_unit_id,
        ]);

        $this->postedEntries[] = $glEntry;

        // Create balancing entry if specified
        if ($line->balancing_account_id) {
            $balancingEntry = $this->createGeneralLedgerEntry([
                'account_id' => $line->balancing_account_id,
                'posting_date' => $line->posting_date,
                'document_type' => $line->document_type,
                'document_no' => $documentNo,
                'description' => $line->description.' (Balancing)',
                'debit_amount' => $line->credit_amount, // Reverse
                'credit_amount' => $line->debit_amount,
                'amount_lcy' => -$line->amount_lcy,
                'source_code' => $line->source_code ?? $template->source_code,
            ]);

            $this->postedEntries[] = $balancingEntry;
        }

        $this->updateLineStatus($line, 'posted', $glEntry->id, GlEntry::class);
    }
}

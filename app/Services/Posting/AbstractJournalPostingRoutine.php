<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Models\GlEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

abstract class AbstractJournalPostingRoutine implements PostingRoutineInterface
{
    protected array $postedEntries = [];

    protected array $errors = [];

    public function validate(object $batch): array
    {
        $this->errors = [];

        // Common validations
        $status = is_string($batch->status) ? $batch->status : ($batch->status->value ?? null);
        if ($status !== 'released') {
            $this->errors[] = 'Batch must be released before posting';
        }

        foreach ($batch->lines as $line) {
            $this->validateLine($line);
        }

        return $this->errors;
    }

    abstract protected function validateLine(object $line): void;

    /**
     * @throws \Throwable
     */
    protected ?int $currentTransactionNumber = null;

    protected ?int $nextEntryNumber = null;

    protected function getTransactionNumber(): int
    {
        if ($this->currentTransactionNumber === null) {
            $this->currentTransactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;
        }

        return $this->currentTransactionNumber;
    }

    protected function getEntryNumber(): int
    {
        if ($this->nextEntryNumber === null) {
            $this->nextEntryNumber = (GlEntry::max('entry_number') ?? 0) + 1;
        }

        return $this->nextEntryNumber++;
    }

    public function post(object $batch): PostingResult
    {
        $errors = $this->validate($batch);
        if (! empty($errors)) {
            return new PostingResult(false, $errors, []);
        }

        return DB::transaction(function () use ($batch) {
            $this->postedEntries = [];
            $this->currentTransactionNumber = null;
            $this->nextEntryNumber = null;

            foreach ($batch->lines as $line) {
                $this->postLine($line);
            }

            $batch->update(['status' => 'posted']);

            return new PostingResult(true, [], $this->postedEntries);
        });
    }

    abstract protected function postLine(object $line): void;

    protected function createGeneralLedgerEntry(array $data): GlEntry
    {
        $data['created_by'] = Auth::id();
        $data['entry_timestamp'] = now();
        if (! isset($data['transaction_number'])) {
            $data['transaction_number'] = $this->getTransactionNumber();
        }
        if (! isset($data['entry_number'])) {
            $data['entry_number'] = $this->getEntryNumber();
        }

        // Map common aliases to ensure strict schema adherence
        if (isset($data['account_id']) && ! isset($data['chart_of_account_id'])) {
            $data['chart_of_account_id'] = $data['account_id'];
        }
        if (isset($data['document_no']) && ! isset($data['document_number'])) {
            $data['document_number'] = $data['document_no'];
        }

        // Calculate amount if missing
        if (! isset($data['amount'])) {
            $data['amount'] = ($data['debit_amount'] ?? 0) - ($data['credit_amount'] ?? 0);
        }

        // Default document properties
        if (! isset($data['document_type'])) {
            $data['document_type'] = 'JOURNAL';
        }
        if (! isset($data['document_date'])) {
            $data['document_date'] = $data['posting_date'] ?? now();
        }

        return GlEntry::create($data);
    }

    protected function updateLineStatus(object $line, string $status, ?int $postedEntryId = null, ?string $postedEntryType = null): void
    {
        $line->update([
            'line_status' => $status,
            'posted_entry_id' => $postedEntryId,
            'posted_entry_type' => $postedEntryType,
        ]);
    }

    public function preview(object $batch): Collection
    {
        $this->errors = $this->validate($batch);

        $previewEntries = new Collection;

        if (empty($this->errors)) {
            // Mock posting for preview
            foreach ($batch->lines as $line) {
                // In a real implementation, we would simulate postLine
                // and collect entries without saving to DB.
            }
        }

        return $previewEntries;
    }

    public function reverse(object $batch, string $reason): void
    {
        // Default implementation for reversal logic
        // Should be overridden by specific routines if needed
        throw new \RuntimeException('Reversal not implemented for this routine.');
    }
}

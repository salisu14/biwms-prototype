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
        if ($batch->status !== 'released') {
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
    public function post(object $batch): PostingResult
    {
        $errors = $this->validate($batch);
        if (! empty($errors)) {
            return new PostingResult(false, $errors, []);
        }

        return DB::transaction(function () use ($batch) {
            $this->postedEntries = [];

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

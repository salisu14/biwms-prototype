<?php

declare(strict_types=1);

namespace App\Services\Posting;

use App\Models\FixedAssetJournalLine;
use Illuminate\Support\Collection;

class FixedAssetJournalPostingRoutine extends AbstractJournalPostingRoutine
{
    /**
     * @param FixedAssetJournalLine $line
     */
    protected function validateLine(object $line): void
    {
        if (!$line->asset_id) {
            $this->errors[] = "Line {$line->line_no}: Asset is required";
        }

        if (!$line->amount) {
            $this->errors[] = "Line {$line->line_no}: Amount is required";
        }
    }

    /**
     * @param FixedAssetJournalLine $line
     */
    protected function postLine(object $line): void
    {
        // TODO: Implement Fixed Asset posting logic
        // 1. Create FA Ledger Entry
        // 2. Create G/L Entries if integrated
        // 3. Update FA Book values
        
        $this->updateLineStatus($line, 'posted');
    }

    public function preview(object $batch): Collection
    {
        return collect([]);
    }

    public function reverse(object $batch, string $reason): void
    {
        // TODO: Implement reversal logic
    }
}

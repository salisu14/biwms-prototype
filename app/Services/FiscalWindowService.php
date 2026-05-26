<?php

namespace App\Services;

use App\Models\FiscalReopenLog;
use App\Models\GeneralLedgerSetup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalWindowService
{
    public function reopenPostingWindow(string $fromDate, string $toDate, string $reason, int $userId): void
    {
        if ($fromDate > $toDate) {
            throw ValidationException::withMessages([
                'allow_posting_from' => 'Allow Posting From cannot be after Allow Posting To.',
            ]);
        }

        DB::transaction(function () use ($fromDate, $toDate, $reason, $userId): void {
            $setup = GeneralLedgerSetup::instance();

            FiscalReopenLog::create([
                'previous_allow_posting_from' => $setup->allow_posting_from,
                'previous_allow_posting_to' => $setup->allow_posting_to,
                'new_allow_posting_from' => $fromDate,
                'new_allow_posting_to' => $toDate,
                'reason' => $reason,
                'requested_by' => $userId,
            ]);

            $setup->update([
                'allow_posting_from' => $fromDate,
                'allow_posting_to' => $toDate,
            ]);
        });
    }
}

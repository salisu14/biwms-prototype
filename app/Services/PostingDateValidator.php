<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\GeneralLedgerSetup;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PostingDateValidator
{
    public function validate(DateTimeInterface|string|null $postingDate): void
    {
        $date = Carbon::parse($postingDate ?? now())->startOfDay();

        if (! Schema::hasTable('general_ledger_setups') || ! Schema::hasTable('accounting_periods')) {
            return;
        }

        $setup = GeneralLedgerSetup::instance();

        if ($setup->allow_posting_from && $date->lt(Carbon::parse($setup->allow_posting_from)->startOfDay())) {
            throw ValidationException::withMessages([
                'posting_date' => 'Posting date is before the allowed posting range.',
            ]);
        }

        if ($setup->allow_posting_to && $date->gt(Carbon::parse($setup->allow_posting_to)->startOfDay())) {
            throw ValidationException::withMessages([
                'posting_date' => 'Posting date is after the allowed posting range.',
            ]);
        }

        $period = AccountingPeriod::query()->containingDate($date)->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'posting_date' => 'No accounting period exists for the selected posting date.',
            ]);
        }

        if ($period->is_closed) {
            throw ValidationException::withMessages([
                'posting_date' => 'The accounting period for this posting date is closed.',
            ]);
        }
    }
}

<?php

namespace App\Events;

use App\Models\BankAccountLedgerEntry;
use App\Models\PayrollDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollSalaryPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PayrollDocument $document,
        public BankAccountLedgerEntry $bankLedgerEntry
    ) {}
}

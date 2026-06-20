<?php

namespace App\Events;

use App\Models\FALedgerEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FixedAssetPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public FALedgerEntry $entry) {}
}

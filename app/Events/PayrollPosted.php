<?php

namespace App\Events;

use App\Models\PayrollDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayrollPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(public PayrollDocument $document) {}
}

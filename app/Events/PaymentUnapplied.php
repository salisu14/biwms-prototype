<?php

namespace App\Events;

use App\Models\PaymentApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentUnapplied
{
    use Dispatchable, SerializesModels;

    public function __construct(public PaymentApplication $application) {}
}

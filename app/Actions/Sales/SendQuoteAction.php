<?php

namespace App\Actions\Sales;

use App\Models\SalesQuote;

class SendQuoteAction
{
    public function execute(SalesQuote $quote)
    {
        $quote->update(['status' => 'sent']);

        // optional: send email / PDF
    }
}

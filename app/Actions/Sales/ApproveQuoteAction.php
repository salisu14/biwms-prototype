<?php

namespace App\Actions\Sales;

use App\Models\SalesQuote;

class ApproveQuoteAction
{
    public function execute(SalesQuote $quote, $userId)
    {
        if ($quote->status !== 'sent') {
            throw new \Exception('Only sent quotes can be approved');
        }

        $quote->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        // Optional: notify sales rep
    }
}

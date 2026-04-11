<?php

namespace App\Observers;

use App\Models\SalesCreditMemoLine;

class SalesCreditMemoLineObserver
{
    /**
     * Handle the SalesCreditMemoLine "creating" event.
     * Automatically calculates the next line number for the specific credit memo.
     */
    public function creating(SalesCreditMemoLine $line): void
    {
        if (empty($line->line_no)) {
            // Find the current highest line number for this specific credit memo
            $maxLineNo = SalesCreditMemoLine::where('sales_credit_memo_id', $line->sales_credit_memo_id)
                ->max('line_no');

            // Increment by 10 (common ERP practice) or 1
            $line->line_no = $maxLineNo ? $maxLineNo + 10 : 10;
        }
    }
}

<?php

namespace App\Actions\Sales;

use App\Models\SalesQuote;

class UpdateQuoteAction
{
    public function execute(SalesQuote $quote, array $data)
    {
        $changes = [];

        foreach ($data['items'] as $i => $item) {
            $original = $quote->items[$i] ?? null;
            if ($original && $original->quantity != $item['qty']) {
                $changes[] = [
                    'item_id' => $item['item_id'],
                    'old_qty' => $original->quantity,
                    'new_qty' => $item['qty'],
                ];
            }
        }

        if (! empty($changes)) {
            $quote->revisions()->create([
                'changes' => json_encode($changes),
                'version' => $quote->revisions()->count() + 1,
            ]);
        }

        // Update quote normally
    }

    //    public function execute(SalesQuote $quote, array $data)
    //    {
    //        $changes = [];
    //
    //        foreach ($data['items'] as $i => $item) {
    //            $original = $quote->items[$i] ?? null;
    //            if ($original && $original->quantity != $item['qty']) {
    //                $changes[] = [
    //                    'item_id' => $item['item_id'],
    //                    'old_qty' => $original->quantity,
    //                    'new_qty' => $item['qty']
    //                ];
    //            }
    //        }
    //
    //        if(!empty($changes)) {
    //            $quote->revisions()->create([
    //                'changes' => json_encode($changes),
    //                'version' => $quote->revisions()->count() + 1
    //            ]);
    //        }

    //        // Update quote normally
    //    }
}

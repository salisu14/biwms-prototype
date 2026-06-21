<?php

namespace App\Actions\Sales;

use App\Models\SalesOrder;
use App\Models\SalesQuote;
use Illuminate\Support\Facades\DB;

class ConvertQuoteToOrderAction
{
    public function execute(SalesQuote $quote)
    {
        if ($quote->status !== 'sent') {
            throw new \Exception('Quote must be sent first');
        }

        return DB::transaction(function () use ($quote) {

            $order = SalesOrder::create([
                'customer_id' => $quote->customer_id,
                'status' => 'pending',
            ]);

            foreach ($quote->items as $item) {
                $order->items()->create([
                    'item_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                ]);
            }

            $quote->update(['status' => 'accepted']);

            return $order;
        });
    }
}

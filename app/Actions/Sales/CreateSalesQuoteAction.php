<?php

namespace App\Actions\Sales;

use App\Models\CustomerPriceOverride;
use App\Models\SalesQuote;
use App\Services\Sales\FinalPricingService;
use Illuminate\Support\Facades\DB;

class CreateSalesQuoteAction
{
    public function execute(array $data)
    {
        return DB::transaction(function () use ($data) {
            $quote = SalesQuote::create([
                'quote_no' => 'SQ-'.now()->timestamp,
                'customer_id' => $data['customer_id'],
                'quote_date' => now(),
                'status' => 'draft',
            ]);

            $pricingService = app(FinalPricingService::class);

            foreach ($data['items'] as $item) {
                // 1. Get base price from service
                $unitPrice = $pricingService->getFinalPrice($item['item_id'], $quote->customer);

                // 2. Check for specific overrides
                $override = CustomerPriceOverride::where('customer_id', $quote->customer_id)
                    ->where('item_id', $item['item_id'])
                    ->first();

                if ($override) {
                    $unitPrice = $override->override_price;
                }

                $quote->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['qty'],
                    'unit_price' => $unitPrice,
                    'line_total' => $item['qty'] * $unitPrice,
                ]);
            }

            // Update total after all items are added
            $quote->update([
                'total_amount' => $quote->items()->sum('line_total'),
            ]);

            return $quote;
        });
    }
}

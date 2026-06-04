<?php

namespace App\Actions\Sales;

use App\Models\Item;
use App\Models\SalesQuote;
use App\Services\Sales\CampaignService;
use App\Services\Sales\SalesPricingResolver;
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

            foreach ($data['items'] as $item) {
                $itemModel = Item::query()->findOrFail($item['item_id']);
                $pricing = app(SalesPricingResolver::class)->resolve(
                    item: $itemModel,
                    customer: $quote->customer,
                    quantity: (float) $item['qty'],
                );
                $unitPrice = app(CampaignService::class)->apply($itemModel, $pricing['unit_price']);

                $quote->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['qty'],
                    'unit_price' => $unitPrice,
                    'discount' => $pricing['discount_amount'],
                    'line_total' => ($item['qty'] * $unitPrice) - $pricing['discount_amount'],
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

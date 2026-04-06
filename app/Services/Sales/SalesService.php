<?php

namespace App\Services\Sales;

use App\Services\PricingService;

class SalesService
{
    public function sell($item, $qty, $customer)
    {
        $price = app(PricingService::class)
            ->resolvePrice($item, $customer);

        $revenue = $price * $qty;

        $cost = app(InventoryService::class)
            ->consume($item->id, $qty);

        $this->post($item, $revenue, $cost, $customer);

        return compact('revenue', 'cost');
    }

    private function post($item, $revenue, $cost, $customer)
    {
        $lines = [
            ['account_id' => 1100, 'debit' => $revenue], // Receivable
            ['account_id' => 4000, 'credit' => $revenue], // Revenue

            ['account_id' => 5000, 'debit' => $cost], // COGS
            ['account_id' => 1300, 'credit' => $cost], // Inventory
        ];

        app(\App\Services\PostingService::class)->post($lines);
    }
}

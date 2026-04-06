<?php

namespace App\Services\Pricing;

use App\Models\PriceList;

class PriceValidationService
{
    public function validateUniquePrice(array $data): void
    {
        $query = PriceList::where('item_id', $data['item_id']);

        if (!empty($data['customer_id'])) {
            $query->where('customer_id', $data['customer_id']);
        }

        if (!empty($data['customer_group_id'])) {
            $query->where('customer_group_id', $data['customer_group_id']);
        }

        // Overlapping date check
        $query->where(function ($q) use ($data) {
            $q->whereBetween('starting_date', [$data['starting_date'], $data['ending_date'] ?? now()])
                ->orWhereBetween('ending_date', [$data['starting_date'], $data['ending_date'] ?? now()]);
        });

        if ($query->exists()) {
            throw new \Exception('A price already exists for this combination and date range.');
        }
    }
}

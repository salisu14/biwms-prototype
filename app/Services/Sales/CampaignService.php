<?php

namespace App\Services\Sales;

use App\Models\Campaign;

class CampaignService
{
    public function apply($item, $price)
    {
        $campaign = Campaign::whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->whereHas('items', fn ($q) => $q->where('item_id', $item->id))
            ->first();

        if (! $campaign) {
            return $price;
        }

        $itemCampaign = $campaign->items
            ->where('item_id', $item->id)
            ->first();

        if ($itemCampaign->special_price) {
            return $itemCampaign->special_price;
        }

        if ($itemCampaign->discount_percent) {
            return $price * (1 - $itemCampaign->discount_percent / 100);
        }

        return $price;
    }
}

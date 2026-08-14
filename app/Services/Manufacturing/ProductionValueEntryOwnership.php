<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionOrder;
use App\Models\ValueEntry;
use Illuminate\Database\Eloquent\Builder;

class ProductionValueEntryOwnership
{
    public function belongsToOrderQuery(ProductionOrder $order): Builder
    {
        return $this->constrainToOrder(ValueEntry::query(), $order);
    }

    public function constrainToOrder(Builder $query, ProductionOrder $order): Builder
    {
        return $query->where(function (Builder $query) use ($order): void {
            $query->where('production_order_no', $order->document_number)
                ->orWhere(function (Builder $fallback) use ($order): void {
                    $fallback->where('source_type', ProductionOrder::class)
                        ->where('source_id', $order->id);
                });
        });
    }
}

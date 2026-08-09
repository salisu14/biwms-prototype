<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionCampaignStatus;
use App\Models\Manufacturing\ProductionCampaign;
use App\Models\Manufacturing\ProductionCampaignOrder;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductionCampaignPlanningService
{
    /**
     * @param  Collection<int, ProductionOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    public function suggestByRouting(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (ProductionOrder $order): string => (string) ($order->routing_id ?: 'unrouted'))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group, string $routingId): array => [
                'grouping_rule' => 'same_routing',
                'grouping_key' => $routingId,
                'order_ids' => $group->pluck('id')->values()->all(),
                'suggested_action' => 'Review as campaign candidate to reduce setup/changeover.',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $productionOrderIds
     */
    public function createPlannerSelected(string $code, string $name, array $productionOrderIds, ?int $workCenterId = null): ProductionCampaign
    {
        return DB::transaction(function () use ($code, $name, $productionOrderIds, $workCenterId): ProductionCampaign {
            $orders = ProductionOrder::query()->whereIn('id', $productionOrderIds)->orderBy('document_number')->get();

            $campaign = ProductionCampaign::query()->create([
                'code' => $code,
                'name' => $name,
                'status' => ProductionCampaignStatus::Draft,
                'work_center_id' => $workCenterId,
                'grouping_rule' => 'planner_selected',
                'grouping_key' => hash('sha256', implode('|', $orders->pluck('id')->all())),
            ]);

            foreach ($orders as $index => $order) {
                ProductionCampaignOrder::query()->create([
                    'production_campaign_id' => $campaign->id,
                    'production_order_id' => $order->id,
                    'sequence' => ($index + 1) * 10000,
                    'planned_quantity_base' => $order->quantity_base,
                ]);
            }

            return $campaign->fresh('orders');
        });
    }
}

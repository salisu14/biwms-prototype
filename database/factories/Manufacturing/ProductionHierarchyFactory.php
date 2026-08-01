<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Enums\ProductionHierarchyReadinessClassification;
use App\Enums\ProductionHierarchyStatus;
use App\Models\Business;
use App\Models\Item;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends Factory<ProductionHierarchy>
 */
class ProductionHierarchyFactory extends Factory
{
    protected $model = ProductionHierarchy::class;

    public function definition(): array
    {
        $productionOrder = $this->productionOrder();

        return [
            'business_id' => Business::query()->firstOrCreate(['code' => 'DEFAULT'], ['name' => 'Default Business'])->id,
            'root_production_order_id' => $productionOrder->id,
            'planning_version' => 1,
            'status' => ProductionHierarchyStatus::Draft,
            'readiness_classification' => ProductionHierarchyReadinessClassification::Ready,
            'max_depth' => 25,
            'node_count' => 0,
            'manufactured_component_count' => 0,
            'planned_quantity_base' => '0',
            'planned_uom_code' => $productionOrder->unit_of_measure_code,
            'created_by' => User::factory(),
            'metadata' => [],
        ];
    }

    private function productionOrder(): ProductionOrder
    {
        $item = Item::factory()->create();
        $user = User::factory()->create();
        $previousUser = Auth::user();

        Auth::setUser($user);

        try {
            return ProductionOrder::query()->forceCreate([
                'document_number' => 'PO-'.fake()->unique()->numerify('######'),
                'status' => 'PLANNED',
                'item_id' => $item->id,
                'quantity' => 1,
                'unit_of_measure_code' => 'PCS',
                'quantity_base' => 1,
            ]);
        } finally {
            if ($previousUser instanceof User) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}

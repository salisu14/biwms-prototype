<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Models\Location;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductionMaterialReservation>
 */
class ProductionMaterialReservationFactory extends Factory
{
    protected $model = ProductionMaterialReservation::class;

    public function definition(): array
    {
        $supplyLink = ProductionOrderSupplyLink::factory()->create();

        return [
            'business_id' => $supplyLink->business_id,
            'production_hierarchy_id' => $supplyLink->production_hierarchy_id,
            'production_hierarchy_node_id' => $supplyLink->production_hierarchy_node_id,
            'production_order_id' => $supplyLink->parent_production_order_id,
            'production_order_component_id' => $supplyLink->parent_component_id,
            'production_order_supply_link_id' => $supplyLink->id,
            'item_id' => $supplyLink->item_id,
            'location_id' => Location::factory(),
            'reservation_type' => ProductionReservationType::ExistingInventory,
            'status' => ProductionReservationStatus::Active,
            'quantity_base' => '1',
            'remaining_quantity_base' => '1',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ];
    }
}

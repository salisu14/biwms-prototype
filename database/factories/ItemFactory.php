<?php

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_code' => $this->faker->unique()->bothify('ITEM-####'),
            'description' => $this->faker->words(3, true),
            'item_type' => ItemType::RAW_MATERIAL,
            'unit_cost' => 10,
            'general_product_posting_group_id' => $this->generalProductPostingGroupId(),
            'inventory_posting_group_id' => $this->inventoryPostingGroupId(),
        ];
    }

    private function generalProductPostingGroupId(): int
    {
        return GeneralProductPostingGroup::query()->firstOrCreate(
            ['code' => 'DEFAULT-ITEM'],
            [
                'description' => 'Default Item Posting Group',
                'default_vat_product_posting_group_id' => null,
                'auto_create_vat_prod_posting_group' => false,
                'blocked' => false,
            ],
        )->id;
    }

    private function inventoryPostingGroupId(): int
    {
        return InventoryPostingGroup::query()->firstOrCreate(
            ['code' => 'DEFAULT-STOCK'],
            [
                'description' => 'Default Inventory Posting Group',
                'blocked' => false,
            ],
        )->id;
    }
}

<?php

namespace App\Services;

use App\DTOs\BusinessDTO;
use App\DTOs\FactoryDTO;
use App\Models\Business;
use App\Models\Factory;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Enums\DimensionValueType;

class OrgEntityService
{
    /**
     * Create or update a Business and its corresponding Dimension Value.
     */
    public function upsertBusiness(BusinessDTO $dto, ?Business $business = null): Business
    {
        $business = $business ?? new Business();
        
        $business->fill($dto->toArray());
        $business->save();

        $this->syncDimensionValue('BUSINESS', $business->code, $business->name, null);

        return $business;
    }

    /**
     * Create or update a Factory and its corresponding Dimension Value.
     */
    public function upsertFactory(FactoryDTO $dto, ?Factory $factory = null): Factory
    {
        $factory = $factory ?? new Factory();
        
        $factory->fill($dto->toArray());
        $factory->save();

        // Find the parent business dimension value ID to establish hierarchy
        $business = Business::find($dto->businessId);
        $parentDimensionValueId = null;
        
        if ($business) {
            $parentDimensionValueId = DimensionValue::where('code', $business->code)
                ->whereHas('dimension', fn($q) => $q->where('code', 'BUSINESS'))
                ->value('id');
        }

        $this->syncDimensionValue('FACTORY', $factory->code, $factory->name, $parentDimensionValueId);

        return $factory;
    }

    /**
     * Synchronize a specific code with the Dimension engine.
     */
    protected function syncDimensionValue(string $dimensionCode, string $valueCode, string $name, ?int $parentId = null): void
    {
        $dimension = Dimension::where('code', $dimensionCode)->first();

        if (! $dimension) {
            return; // Sub-system dimension doesn't exist
        }

        DimensionValue::updateOrCreate(
            [
                'dimension_id' => $dimension->id,
                'code' => $valueCode,
            ],
            [
                'name' => $name,
                'dimension_value_type' => DimensionValueType::Standard,
                'parent_id' => $parentId,
            ]
        );
    }

    /**
     * Remove the dimension counterpart for a business.
     */
    public function deleteBusinessDimension(Business $business): void
    {
        $this->removeDimensionValue('BUSINESS', $business->code);
    }

    /**
     * Remove the dimension counterpart for a factory.
     */
    public function deleteFactoryDimension(Factory $factory): void
    {
        $this->removeDimensionValue('FACTORY', $factory->code);
    }

    protected function removeDimensionValue(string $dimensionCode, string $valueCode): void
    {
        $dimension = Dimension::where('code', $dimensionCode)->first();
        if ($dimension) {
            DimensionValue::where('dimension_id', $dimension->id)
                ->where('code', $valueCode)
                ->delete();
        }
    }
}

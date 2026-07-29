<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionVarianceType: string
{
    case MaterialPrice = 'material_price';
    case MaterialQuantity = 'material_quantity';
    case CapacityRate = 'capacity_rate';
    case CapacityEfficiency = 'capacity_efficiency';
    case CapacityOverhead = 'capacity_overhead';
    case StandardCost = 'standard_cost';
    case Yield = 'yield';
    case Rounding = 'rounding';
    case ControlledOther = 'controlled_other';

    public function costComponent(): ManufacturingCostComponent
    {
        return match ($this) {
            self::MaterialPrice => ManufacturingCostComponent::MaterialPriceVariance,
            self::MaterialQuantity => ManufacturingCostComponent::MaterialQuantityVariance,
            self::CapacityRate => ManufacturingCostComponent::CapacityRateVariance,
            self::CapacityEfficiency => ManufacturingCostComponent::CapacityEfficiencyVariance,
            self::CapacityOverhead => ManufacturingCostComponent::CapacityOverheadVariance,
            self::StandardCost => ManufacturingCostComponent::StandardCostVariance,
            self::Yield => ManufacturingCostComponent::OutputQuantityVariance,
            self::Rounding => ManufacturingCostComponent::RoundingVariance,
            self::ControlledOther => ManufacturingCostComponent::Variance,
        };
    }
}

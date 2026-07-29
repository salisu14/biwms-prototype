<?php

declare(strict_types=1);

namespace App\Enums;

enum ManufacturingCostComponent: string
{
    case DirectMaterial = 'direct_material';
    case DirectCapacity = 'direct_capacity';
    case CapacityOverhead = 'capacity_overhead';
    case ExpectedDirectMaterial = 'expected_direct_material';
    case ExpectedDirectCapacity = 'expected_direct_capacity';
    case ExpectedCapacityOverhead = 'expected_capacity_overhead';
    case ExpectedOutput = 'expected_output';
    case ExpectedCostClearing = 'expected_cost_clearing';
    case MaterialOverhead = 'material_overhead';
    case Subcontracting = 'subcontracting';
    case Output = 'output';
    case Variance = 'variance';
    case MaterialPriceVariance = 'material_price_variance';
    case MaterialQuantityVariance = 'material_quantity_variance';
    case CapacityRateVariance = 'capacity_rate_variance';
    case CapacityEfficiencyVariance = 'capacity_efficiency_variance';
    case CapacityOverheadVariance = 'capacity_overhead_variance';
    case OutputQuantityVariance = 'output_quantity_variance';
    case RoundingVariance = 'rounding_variance';
    case StandardCostVariance = 'standard_cost_variance';
    case CostAdjustment = 'cost_adjustment';

    public function label(): string
    {
        return match ($this) {
            self::DirectMaterial => 'Direct Material',
            self::DirectCapacity => 'Direct Capacity',
            self::CapacityOverhead => 'Capacity Overhead',
            self::ExpectedDirectMaterial => 'Expected Direct Material',
            self::ExpectedDirectCapacity => 'Expected Direct Capacity',
            self::ExpectedCapacityOverhead => 'Expected Capacity Overhead',
            self::ExpectedOutput => 'Expected Output',
            self::ExpectedCostClearing => 'Expected Cost Clearing',
            self::MaterialOverhead => 'Material Overhead',
            self::Subcontracting => 'Subcontracting',
            self::Output => 'Output',
            self::Variance => 'Variance',
            self::MaterialPriceVariance => 'Material Price Variance',
            self::MaterialQuantityVariance => 'Material Quantity Variance',
            self::CapacityRateVariance => 'Capacity Rate Variance',
            self::CapacityEfficiencyVariance => 'Capacity Efficiency Variance',
            self::CapacityOverheadVariance => 'Capacity Overhead Variance',
            self::OutputQuantityVariance => 'Output Quantity Variance',
            self::RoundingVariance => 'Rounding Variance',
            self::StandardCostVariance => 'Standard Cost Variance',
            self::CostAdjustment => 'Cost Adjustment',
        };
    }

    public function isVariance(): bool
    {
        return in_array($this, [
            self::Variance,
            self::MaterialPriceVariance,
            self::MaterialQuantityVariance,
            self::CapacityRateVariance,
            self::CapacityEfficiencyVariance,
            self::CapacityOverheadVariance,
            self::OutputQuantityVariance,
            self::RoundingVariance,
            self::StandardCostVariance,
        ], true);
    }
}

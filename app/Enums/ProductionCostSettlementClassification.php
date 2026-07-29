<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionCostSettlementClassification: string
{
    case Ready = 'ready';
    case UnallocatedCost = 'unallocated_cost';
    case PendingExpectedCost = 'pending_expected_cost';
    case PendingMaterialCost = 'pending_material_cost';
    case PendingActualMaterialCost = 'pending_actual_material_cost';
    case PendingCapacityCost = 'pending_capacity_cost';
    case PendingOverheadCost = 'pending_overhead_cost';
    case RoundingResidual = 'rounding_residual';
    case TrueProductionVariance = 'true_production_variance';
    case LateCostAdjustmentRequired = 'late_cost_adjustment_required';
    case PostingSetupMissing = 'posting_setup_missing';
    case CostingPeriodClosed = 'costing_period_closed';
    case UnsupportedCostComponent = 'unsupported_cost_component';
    case RequiredOutputNotPosted = 'required_output_not_posted';
    case RequiredConsumptionNotPosted = 'required_consumption_not_posted';
    case RequiredCapacityNotPosted = 'required_capacity_not_posted';
    case UnresolvedProductionJournalLines = 'unresolved_production_journal_lines';
    case ProductionOrderNotOperationallyFinished = 'production_order_not_operationally_finished';
}

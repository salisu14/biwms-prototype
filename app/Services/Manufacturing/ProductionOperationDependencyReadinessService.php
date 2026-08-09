<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionOperationDependencyReadiness;
use App\Enums\ProductionOperationDependencyStatus;
use App\Enums\ProductionOperationDependencyType;
use App\Enums\ProductionOperationExecutionStatus;
use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOperationDependency;
use App\Models\Manufacturing\ProductionOperationExecution;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Support\DecimalMath;

class ProductionOperationDependencyReadinessService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function findingsForRoutingLine(ProductionOrderRoutingLine $routingLine): array
    {
        return ProductionOperationDependency::query()
            ->with(['upstreamProductionOrder', 'upstreamRoutingLine', 'downstreamProductionOrder', 'downstreamRoutingLine', 'supplyLink.materialReservations'])
            ->where('downstream_routing_line_id', $routingLine->id)
            ->whereNotIn('status', [
                ProductionOperationDependencyStatus::Cancelled->value,
                ProductionOperationDependencyStatus::Invalid->value,
            ])
            ->orderBy('sequence')
            ->get()
            ->map(fn (ProductionOperationDependency $dependency): array => $this->findingForDependency($dependency))
            ->filter(fn (array $finding): bool => $finding['classification'] !== ProductionOperationDependencyReadiness::Ready->value)
            ->values()
            ->all();
    }

    public function readinessForRoutingLine(ProductionOrderRoutingLine $routingLine): ProductionOperationReadinessResult
    {
        $findings = $this->findingsForRoutingLine($routingLine);

        if ($findings === []) {
            return new ProductionOperationReadinessResult(ProductionOperationDependencyReadiness::Ready, true);
        }

        $classification = ProductionOperationDependencyReadiness::from($findings[0]['classification']);

        return new ProductionOperationReadinessResult($classification, false, $findings);
    }

    public function readinessForExecution(ProductionOperationExecution $execution): ProductionOperationReadinessResult
    {
        $execution->loadMissing('routingLine');

        return $this->readinessForRoutingLine($execution->routingLine);
    }

    /**
     * @return array<string, mixed>
     */
    public function findingForDependency(ProductionOperationDependency $dependency): array
    {
        $dependency->loadMissing(['upstreamProductionOrder', 'upstreamRoutingLine', 'supplyLink.materialReservations']);

        if (! $dependency->upstreamProductionOrder || ! $dependency->downstreamProductionOrder || ! $dependency->upstreamRoutingLine || ! $dependency->downstreamRoutingLine) {
            return $this->finding($dependency, ProductionOperationDependencyReadiness::InvalidDependency, 'Dependency references a missing production order or routing operation.', 'Replan or regenerate execution dependencies.');
        }

        if ($dependency->upstreamProductionOrder->status === ProductionOrderStatus::CANCELLED) {
            return $this->finding($dependency, ProductionOperationDependencyReadiness::UpstreamCancelled, 'Upstream production order is cancelled.', 'Cancel or replan the dependent operation.');
        }

        if ($this->qualityBlocked($dependency)) {
            return $this->finding($dependency, ProductionOperationDependencyReadiness::WaitingForQualityRelease, 'Upstream operation output is blocked by an active quality hold.', 'Release or resolve the upstream quality hold.');
        }

        if ($dependency->dependency_type === ProductionOperationDependencyType::FinishToStart && ! $this->upstreamOperationComplete($dependency)) {
            return $this->finding($dependency, ProductionOperationDependencyReadiness::WaitingForUpstreamOperation, 'Upstream operation is not complete.', 'Complete the upstream operation first.');
        }

        if (in_array($dependency->dependency_type, [
            ProductionOperationDependencyType::OutputAvailableToStart,
            ProductionOperationDependencyType::SupplyAvailableToStart,
            ProductionOperationDependencyType::QualityReleasedToStart,
        ], true)) {
            $fulfilledQuantityBase = DecimalMath::quantity($dependency->supplyLink?->supplied_quantity_base ?? $dependency->fulfilled_quantity_base);
            $minimumStartQuantityBase = DecimalMath::quantity($dependency->minimum_start_quantity_base ?: $dependency->required_quantity_base);

            if (! DecimalMath::isPositive($fulfilledQuantityBase)) {
                return $this->finding($dependency, ProductionOperationDependencyReadiness::WaitingForUpstreamOutput, 'Upstream output is not available yet.', 'Post child/intermediate output and sync supply.');
            }

            if (DecimalMath::compare($fulfilledQuantityBase, $minimumStartQuantityBase) < 0) {
                return $this->finding($dependency, ProductionOperationDependencyReadiness::PartiallyReady, 'Only partial upstream output is available.', 'Wait for the minimum start quantity or lower the approved start threshold.');
            }
        }

        return $this->finding($dependency, ProductionOperationDependencyReadiness::Ready, 'Dependency is satisfied.', null);
    }

    private function upstreamOperationComplete(ProductionOperationDependency $dependency): bool
    {
        return ProductionOperationExecution::query()
            ->where('routing_line_id', $dependency->upstream_routing_line_id)
            ->whereIn('status', [
                ProductionOperationExecutionStatus::Completed,
                ProductionOperationExecutionStatus::Submitted,
                ProductionOperationExecutionStatus::Posted,
            ])
            ->exists();
    }

    private function qualityBlocked(ProductionOperationDependency $dependency): bool
    {
        return ProductionOperationExecution::query()
            ->where('routing_line_id', $dependency->upstream_routing_line_id)
            ->whereHas('activeQualityHolds')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(
        ProductionOperationDependency $dependency,
        ProductionOperationDependencyReadiness $classification,
        string $reason,
        ?string $remediation,
    ): array {
        return [
            'dependency_id' => $dependency->id,
            'classification' => $classification->value,
            'status' => $dependency->status?->value,
            'upstream_production_order_id' => $dependency->upstream_production_order_id,
            'upstream_routing_line_id' => $dependency->upstream_routing_line_id,
            'downstream_production_order_id' => $dependency->downstream_production_order_id,
            'downstream_routing_line_id' => $dependency->downstream_routing_line_id,
            'required_quantity_base' => (string) $dependency->required_quantity_base,
            'minimum_start_quantity_base' => (string) ($dependency->minimum_start_quantity_base ?: $dependency->required_quantity_base),
            'fulfilled_quantity_base' => (string) ($dependency->supplyLink?->supplied_quantity_base ?? $dependency->fulfilled_quantity_base),
            'reason' => $reason,
            'remediation' => $remediation,
        ];
    }
}

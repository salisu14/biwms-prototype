<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProductionOperationDependencyReadiness;
use App\Enums\ProductionOperationDependencyStatus;
use App\Enums\ProductionOperationExecutionStatus;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionIntermediateHandoff;
use App\Models\Manufacturing\ProductionOperationDependency;
use App\Models\Manufacturing\ProductionOperationExecution;
use App\Services\Manufacturing\ProductionOperationDependencyGenerationService;
use App\Services\Manufacturing\ProductionOperationDependencyReadinessService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:manufacturing-hierarchy-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--export= : Write the JSON report to a file path}')]
#[Description('Report Phase 2B manufacturing hierarchy dependency, handoff, and genealogy consistency issues without writing data.')]
class BiwmsManufacturingHierarchyReconcile extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('production_operation_dependencies') || ! Schema::hasTable('production_intermediate_handoffs')) {
            $report = [
                'schema_pending' => [[
                    'classification' => 'schema_pending',
                    'severity' => 'warning',
                    'message' => 'Phase 2B manufacturing dependency tables are not present on this database connection.',
                    'remediation' => 'Run the pending production operation dependency and intermediate handoff migrations before using Phase 2B reconciliation.',
                ]],
            ];

            if ($exportPath = $this->option('export')) {
                $this->exportReport($report, (string) $exportPath);
            }

            if ($this->option('json')) {
                $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            $this->warn('BIWMS Manufacturing Hierarchy Reconciliation');
            $this->line('Mode: report-only. No dependency, handoff, genealogy, ledger, or posting records were changed.');
            $this->section('schema pending', $report['schema_pending'], (bool) $this->option('details'));

            return self::SUCCESS;
        }

        $report = [
            'dependency_without_valid_upstream_operation' => $this->dependenciesMissingOperation('upstream'),
            'dependency_without_valid_downstream_operation' => $this->dependenciesMissingOperation('downstream'),
            'dependency_cycle' => $this->dependencyCycles(),
            'duplicate_active_dependency' => $this->duplicateActiveDependencies(),
            'dependency_fulfilled_while_upstream_incomplete' => $this->fulfilledWhileUpstreamIncomplete(),
            'dependency_blocked_though_ready' => $this->blockedThoughReady(),
            'downstream_operation_started_before_dependency_satisfied' => $this->downstreamStartedBeforeReady(),
            'child_output_available_dependency_not_updated' => $this->childOutputAvailableButNotUpdated(),
            'handoff_quantity_exceeds_child_output' => $this->handoffAvailableExceedsDependency(),
            'handoff_consumed_exceeds_available' => $this->handoffConsumedExceedsAvailable(),
            'genealogy_reference_missing' => $this->handoffsWithMissingLedgerReference(),
            'cancelled_upstream_with_active_ready_dependency' => $this->cancelledUpstreamWithReadyDependency(),
            'completed_downstream_with_unresolved_required_dependency' => $this->completedDownstreamWithUnresolvedDependency(),
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Manufacturing Hierarchy Reconciliation');
        $this->line('Mode: report-only. No dependency, handoff, genealogy, ledger, or posting records were changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->newLine();

        foreach ($report as $label => $findings) {
            $this->section(str_replace('_', ' ', $label), $findings, (bool) $this->option('details'));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dependenciesMissingOperation(string $side): array
    {
        $relation = $side === 'upstream' ? 'upstreamRoutingLine' : 'downstreamRoutingLine';

        return ProductionOperationDependency::query()
            ->whereDoesntHave($relation)
            ->get()
            ->map(fn (ProductionOperationDependency $dependency): array => $this->finding(
                "dependency_without_valid_{$side}_operation",
                'critical',
                $dependency,
                "{$side} routing operation is missing.",
            ))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dependencyCycles(): array
    {
        $hierarchyIds = ProductionOperationDependency::query()
            ->whereNotNull('production_hierarchy_id')
            ->distinct()
            ->pluck('production_hierarchy_id');

        $findings = [];
        foreach ($hierarchyIds as $hierarchyId) {
            $hierarchy = ProductionHierarchy::query()->find($hierarchyId);
            if (! $hierarchy) {
                continue;
            }

            try {
                app(ProductionOperationDependencyGenerationService::class)->assertAcyclic($hierarchy);
            } catch (\RuntimeException $exception) {
                $findings[] = [
                    'classification' => 'dependency_cycle',
                    'severity' => 'critical',
                    'production_hierarchy_id' => $hierarchyId,
                    'message' => $exception->getMessage(),
                    'remediation' => 'Review generated operation dependencies and replan the hierarchy.',
                ];
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function duplicateActiveDependencies(): array
    {
        return ProductionOperationDependency::query()
            ->selectRaw('min(id) as id, upstream_production_order_id, coalesce(upstream_routing_line_id, 0) as upstream_routing_line_id, downstream_production_order_id, coalesce(downstream_routing_line_id, 0) as downstream_routing_line_id, dependency_type, count(*) as duplicate_count')
            ->whereNotIn('status', [ProductionOperationDependencyStatus::Cancelled->value, ProductionOperationDependencyStatus::Invalid->value])
            ->groupByRaw('upstream_production_order_id, coalesce(upstream_routing_line_id, 0), downstream_production_order_id, coalesce(downstream_routing_line_id, 0), dependency_type')
            ->havingRaw('count(*) > 1')
            ->get()
            ->map(fn ($row): array => [
                'classification' => 'duplicate_active_dependency',
                'severity' => 'critical',
                'dependency_id' => (int) $row->id,
                'duplicate_count' => (int) $row->duplicate_count,
                'remediation' => 'Cancel invalid duplicates and regenerate dependencies.',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fulfilledWhileUpstreamIncomplete(): array
    {
        return ProductionOperationDependency::query()
            ->with('upstreamRoutingLine')
            ->where('status', ProductionOperationDependencyStatus::Fulfilled->value)
            ->get()
            ->filter(fn (ProductionOperationDependency $dependency): bool => ! $dependency->downstreamRoutingLine || ! app(ProductionOperationDependencyReadinessService::class)->readinessForRoutingLine($dependency->downstreamRoutingLine)->ready)
            ->map(fn (ProductionOperationDependency $dependency): array => $this->finding('dependency_fulfilled_while_upstream_incomplete', 'critical', $dependency, 'Dependency is fulfilled but readiness check is not ready.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blockedThoughReady(): array
    {
        return ProductionOperationDependency::query()
            ->with('downstreamRoutingLine')
            ->where('status', ProductionOperationDependencyStatus::Blocked->value)
            ->get()
            ->filter(fn (ProductionOperationDependency $dependency): bool => $dependency->downstreamRoutingLine && app(ProductionOperationDependencyReadinessService::class)->findingForDependency($dependency)['classification'] === ProductionOperationDependencyReadiness::Ready->value)
            ->map(fn (ProductionOperationDependency $dependency): array => $this->finding('dependency_blocked_though_ready', 'warning', $dependency, 'Dependency should be resynced to ready.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function downstreamStartedBeforeReady(): array
    {
        return ProductionOperationExecution::query()
            ->with('routingLine')
            ->whereIn('status', [
                ProductionOperationExecutionStatus::SetupStarted->value,
                ProductionOperationExecutionStatus::SetupCompleted->value,
                ProductionOperationExecutionStatus::Running->value,
                ProductionOperationExecutionStatus::Completed->value,
                ProductionOperationExecutionStatus::Submitted->value,
                ProductionOperationExecutionStatus::Posted->value,
            ])
            ->get()
            ->filter(fn (ProductionOperationExecution $execution): bool => $execution->routingLine && ! app(ProductionOperationDependencyReadinessService::class)->readinessForExecution($execution)->ready)
            ->map(fn (ProductionOperationExecution $execution): array => [
                'classification' => 'downstream_operation_started_before_dependency_satisfied',
                'severity' => 'critical',
                'execution_id' => $execution->id,
                'routing_line_id' => $execution->routing_line_id,
                'production_order_id' => $execution->production_order_id,
                'remediation' => 'Review execution history and upstream dependency state.',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function childOutputAvailableButNotUpdated(): array
    {
        return ProductionOperationDependency::query()
            ->with('supplyLink')
            ->whereIn('status', [
                ProductionOperationDependencyStatus::Planned->value,
                ProductionOperationDependencyStatus::Blocked->value,
            ])
            ->get()
            ->filter(fn (ProductionOperationDependency $dependency): bool => (float) ($dependency->supplyLink?->supplied_quantity_base ?? 0) > 0)
            ->map(fn (ProductionOperationDependency $dependency): array => $this->finding('child_output_available_dependency_not_updated', 'warning', $dependency, 'Child output is available but dependency was not progressed.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handoffAvailableExceedsDependency(): array
    {
        return ProductionIntermediateHandoff::query()
            ->with('dependency')
            ->get()
            ->filter(fn (ProductionIntermediateHandoff $handoff): bool => (float) $handoff->quantity_available_base > (float) ($handoff->dependency?->fulfilled_quantity_base ?? $handoff->quantity_required_base))
            ->map(fn (ProductionIntermediateHandoff $handoff): array => $this->handoffFinding('handoff_quantity_exceeds_child_output', 'critical', $handoff, 'Handoff available quantity exceeds dependency fulfilled quantity.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handoffConsumedExceedsAvailable(): array
    {
        return ProductionIntermediateHandoff::query()
            ->whereRaw('quantity_transferred_base > quantity_available_base')
            ->get()
            ->map(fn (ProductionIntermediateHandoff $handoff): array => $this->handoffFinding('handoff_consumed_exceeds_available', 'critical', $handoff, 'Handoff consumed quantity exceeds available quantity.'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function handoffsWithMissingLedgerReference(): array
    {
        return ProductionIntermediateHandoff::query()
            ->whereNotNull('child_output_item_ledger_entry_id')
            ->whereDoesntHave('childOutputItemLedgerEntry')
            ->get()
            ->map(fn (ProductionIntermediateHandoff $handoff): array => $this->handoffFinding('genealogy_reference_missing', 'critical', $handoff, 'Handoff references a missing child output ledger entry.'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cancelledUpstreamWithReadyDependency(): array
    {
        return ProductionOperationDependency::query()
            ->whereIn('status', [
                ProductionOperationDependencyStatus::Ready->value,
                ProductionOperationDependencyStatus::Fulfilled->value,
            ])
            ->whereHas('upstreamProductionOrder', fn ($query) => $query->where('status', 'CANCELLED'))
            ->get()
            ->map(fn (ProductionOperationDependency $dependency): array => $this->finding('cancelled_upstream_with_active_ready_dependency', 'critical', $dependency, 'Upstream order is cancelled but dependency remains ready.'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function completedDownstreamWithUnresolvedDependency(): array
    {
        return ProductionOperationDependency::query()
            ->whereNotIn('status', [
                ProductionOperationDependencyStatus::Ready->value,
                ProductionOperationDependencyStatus::Fulfilled->value,
            ])
            ->whereHas('downstreamRoutingLine.operationExecutions', fn ($query) => $query->whereIn('status', [
                ProductionOperationExecutionStatus::Completed->value,
                ProductionOperationExecutionStatus::Submitted->value,
                ProductionOperationExecutionStatus::Posted->value,
            ]))
            ->get()
            ->map(fn (ProductionOperationDependency $dependency): array => $this->finding('completed_downstream_with_unresolved_required_dependency', 'critical', $dependency, 'Downstream operation completed while required dependency is unresolved.'))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $classification, string $severity, ProductionOperationDependency $dependency, string $message): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'dependency_id' => $dependency->id,
            'production_hierarchy_id' => $dependency->production_hierarchy_id,
            'upstream_routing_line_id' => $dependency->upstream_routing_line_id,
            'downstream_routing_line_id' => $dependency->downstream_routing_line_id,
            'message' => $message,
            'remediation' => 'Regenerate dependencies or review the related hierarchy/supply link.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handoffFinding(string $classification, string $severity, ProductionIntermediateHandoff $handoff, string $message): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'handoff_id' => $handoff->id,
            'dependency_id' => $handoff->production_operation_dependency_id,
            'message' => $message,
            'remediation' => 'Resync the related dependency from the authoritative supply link.',
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $report
     */
    private function exportReport(array $report, string $path): void
    {
        $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function section(string $title, array $findings, bool $details): void
    {
        $this->line(str($title)->title().': '.count($findings));

        if (! $details) {
            return;
        }

        foreach ($findings as $finding) {
            $this->line(' - ['.$finding['severity'].'] '.$finding['classification'].' '.json_encode($finding, JSON_UNESCAPED_SLASHES));
        }

        $this->newLine();
    }
}

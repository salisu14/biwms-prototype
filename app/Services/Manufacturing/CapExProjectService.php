<?php

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CapExProject;
use App\Models\Manufacturing\CapExProjectLine;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\FixedAsset;
use Illuminate\Support\Facades\DB;

class CapExProjectService
{
    /**
     * Create new CapEx project from approved capital request
     */
    public function createProject(array $data): CapExProject
    {
        return DB::transaction(function () use ($data) {
            $project = CapExProject::create([
                'project_number' => $this->generateProjectNumber(),
                'description' => $data['description'],
                'budget_amount' => $data['budget_amount'],
                'planned_start_date' => $data['planned_start_date'],
                'planned_end_date' => $data['planned_end_date'],
                'wip_gl_account_id' => $data['wip_gl_account_id'],
                'capex_gl_account_id' => $data['capex_gl_account_id'],
                'project_manager_id' => $data['project_manager_id'],
                'created_by' => auth()->id(),
                'status' => 'PLANNING',
            ]);

            // Create initial budget lines
            foreach ($data['budget_lines'] ?? [] as $line) {
                $project->lines()->create([
                    'line_number' => $line['line_number'],
                    'line_type' => $line['line_type'],
                    'description' => $line['description'],
                    'budget_amount' => $line['amount'],
                    'eligible_for_capitalization' => $this->isEligibleForCapitalization($line['line_type']),
                ]);
            }

            return $project;
        });
    }

    /**
     * Link production order costs to CapEx project (for assets under construction)
     */
    public function captureProductionCosts(
        int $projectId,
        int $productionOrderId,
        ?array $options = []
    ): void {
        $project = CapExProject::findOrFail($projectId);
        $order = ProductionOrder::with(['components', 'routingLines.capacityEntries'])->findOrFail($productionOrderId);

        DB::transaction(function () use ($project, $order, $options) {
            $captureMaterials = $options['capture_materials'] ?? $project->capitalize_materials;
            $captureLabor = $options['capture_labor'] ?? $project->capitalize_labor;
            $captureOverhead = $options['capture_overhead'] ?? $project->capitalize_overhead;

            // 1. Capture material costs (if eligible)
            if ($captureMaterials) {
                foreach ($order->components as $component) {
                    $cost = $component->actual_quantity_consumed * $component->unit_cost;

                    if ($cost >= $project->capitalization_threshold) {
                        CapExProjectLine::create([
                            'capex_project_id' => $project->id,
                            'line_number' => $this->getNextLineNumber($project),
                            'line_type' => 'MATERIAL',
                            'description' => "PO {$order->document_number} - {$component->item->description}",
                            'actual_amount' => $cost,
                            'source_document_type' => 'PRODUCTION_ORDER',
                            'source_document_id' => $order->id,
                            'source_document_no' => $order->document_number,
                            'production_order_id' => $order->id,
                            'production_order_component_id' => $component->id,
                            'eligible_for_capitalization' => true,
                        ]);

                        $project->increment('actual_amount', $cost);
                    }
                }
            }

            // 2. Capture labor and machine costs (capacity entries)
            if ($captureLabor || $captureOverhead) {
                foreach ($order->routingLines as $routingLine) {
                    foreach ($routingLine->capacityEntries as $entry) {
                        $laborCost = $entry->direct_cost;
                        $overheadCost = $entry->overhead_cost;

                        if ($captureLabor && $laborCost > 0) {
                            $this->createCapExLine($project, $order, $entry, 'LABOR', $laborCost);
                        }

                        if ($captureOverhead && $overheadCost > 0) {
                            $this->createCapExLine($project, $order, $entry, 'OVERHEAD', $overheadCost);
                        }
                    }
                }
            }

            // Update project totals
            $project->update(['actual_amount' => $project->lines()->sum('actual_amount')]);
        });
    }

    /**
     * Capitalize completed project to Fixed Asset
     */
    public function capitalizeProject(int $projectId, array $assetData): FixedAsset
    {
        $project = CapExProject::with('lines')->findOrFail($projectId);

        return DB::transaction(function () use ($project, $assetData) {
            // Validate all lines are ready
            $uncapitalizedLines = $project->lines()
                ->where('eligible_for_capitalization', true)
                ->where('capitalized', false)
                ->count();

            if ($uncapitalizedLines === 0) {
                throw new \Exception('No eligible costs to capitalize');
            }

            // 1. Create Fixed Asset
            $asset = FixedAsset::create([
                'code' => $assetData['asset_code'] ?? $project->project_number,
                'description' => $assetData['description'] ?? $project->description,
                'asset_type' => $assetData['asset_type'] ?? 'MACHINERY',
                'capex_project_id' => $project->id,
                'acquisition_date' => now(),
                'acquisition_cost' => $project->actual_amount,
                'net_book_value' => $project->actual_amount,
                'useful_life_years' => $assetData['useful_life_years'] ?? 10,
                'depreciation_method' => $assetData['depreciation_method'] ?? 'STRAIGHT_LINE',
                'salvage_value' => $assetData['salvage_value'] ?? 0,
                'annual_capacity_minutes' => $assetData['annual_capacity_minutes'] ?? null,
                'efficiency_percent' => $assetData['efficiency_percent'] ?? 100,
            ]);

            // Calculate annual depreciation
            $depreciableAmount = $asset->acquisition_cost - $asset->salvage_value;
            $asset->annual_depreciation_amount = $depreciableAmount / $asset->useful_life_years;
            $asset->save();

            // 2. Mark project lines as capitalized
            $project->lines()
                ->where('eligible_for_capitalization', true)
                ->update([
                    'capitalized' => true,
                    'capitalized_at' => now(),
                    'capitalized_by' => auth()->id(),
                ]);

            // 3. Update project status
            $project->update([
                'status' => 'COMPLETED',
                'capitalized_amount' => $project->actual_amount,
                'actual_end_date' => now(),
                'fixed_asset_id' => $asset->id,
            ]);

            // 4. Create GL entries
            $this->createCapitalizationJournalEntries($project, $asset);

            // 5. Link to work center if specified
            if (!empty($assetData['work_center_id'])) {
                $asset->workCenters()->attach($assetData['work_center_id'], [
                    'allocation_percentage' => 100,
                    'installation_date' => now(),
                ]);
            }

            return $asset;
        });
    }

    /**
     * Handle interest capitalization during construction period
     */
    public function capitalizeInterest(int $projectId, float $interestAmount, \DateTime $periodEnd): void
    {
        $project = CapExProject::findOrFail($projectId);

        if ($project->status !== 'IN_PROGRESS') {
            throw new \Exception('Interest can only be capitalized during active construction');
        }

        CapExProjectLine::create([
            'capex_project_id' => $project->id,
            'line_number' => $this->getNextLineNumber($project),
            'line_type' => 'INTEREST',
            'description' => "Capitalized interest - {$periodEnd->format('M Y')}",
            'actual_amount' => $interestAmount,
            'eligible_for_capitalization' => true,
        ]);

        $project->increment('actual_amount', $interestAmount);
    }

    /**
     * Transfer WIP to expense (for non-capitalizable costs)
     */
    public function expenseCosts(int $projectId, array $lineIds): void
    {
        $project = CapExProject::findOrFail($projectId);

        $lines = $project->lines()->whereIn('id', $lineIds)->get();

        foreach ($lines as $line) {
            // Create expense GL entry instead of capitalization
            $line->update([
                'eligible_for_capitalization' => false,
                'capitalized' => true, // Mark as processed
                'capitalized_at' => now(),
            ]);

            // Post to OpEx account
            // GL::post([
            //     'debit' => 'MANUFACTURING_OVERHEAD_OPEX',
            //     'credit' => 'WIP_CAPEX_PROJECT',
            //     'amount' => $line->actual_amount,
            // ]);
        }
    }

    // Protected helpers...

    protected function generateProjectNumber(): string
    {
        $prefix = 'CPX';
        $year = date('Y');
        $sequence = CapExProject::whereYear('created_at', $year)->count() + 1;
        return "{$prefix}-{$year}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    protected function getNextLineNumber(CapExProject $project): int
    {
        return ($project->lines()->max('line_number') ?? 0) + 10000;
    }

    protected function isEligibleForCapitalization(string $lineType): bool
    {
        return in_array($lineType, ['MATERIAL', 'LABOR', 'EXTERNAL_SERVICE', 'TOOLING', 'INTEREST']);
    }

    protected function createCapExLine(
        CapExProject $project,
        ProductionOrder $order,
        $capacityEntry,
        string $type,
        float $amount
    ): void {
        if ($amount < $project->capitalization_threshold) {
            return;
        }

        CapExProjectLine::create([
            'capex_project_id' => $project->id,
            'line_number' => $this->getNextLineNumber($project),
            'line_type' => $type,
            'description' => "PO {$order->document_number} - {$type} cost",
            'actual_amount' => $amount,
            'source_document_type' => 'PRODUCTION_ORDER',
            'source_document_id' => $order->id,
            'source_document_no' => $order->document_number,
            'production_order_id' => $order->id,
            'capacity_ledger_entry_id' => $capacityEntry->id,
            'eligible_for_capitalization' => true,
        ]);

        $project->increment('actual_amount', $amount);
    }

    protected function createCapitalizationJournalEntries(CapExProject $project, FixedAsset $asset): void
    {
        // Debit: Fixed Asset (Balance Sheet)
        // Credit: WIP - CapEx Project (Balance Sheet)

        // This would integrate with your GL service:
        // GeneralLedgerService::post([
        //     [
        //         'account_id' => $project->capex_gl_account_id,
        //         'debit' => $project->capitalized_amount,
        //         'credit' => 0,
        //         'reference' => $asset->code,
        //         'description' => "CapEx capitalization: {$project->description}",
        //     ],
        //     [
        //         'account_id' => $project->wip_gl_account_id,
        //         'debit' => 0,
        //         'credit' => $project->capitalized_amount,
        //         'reference' => $asset->code,
        //         'description' => "Transfer from WIP: {$project->project_number}",
        //     ],
        // ]);
    }
}

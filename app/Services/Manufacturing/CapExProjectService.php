<?php

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\CapExProject;
use App\Models\Manufacturing\CapExProjectLine;
use App\Models\Manufacturing\FixedAsset;
use App\Models\Manufacturing\ProductionOrder;
use App\Services\Finance\GeneralLedgerService;
use DomainException;
use Illuminate\Support\Facades\DB;

class CapExProjectService
{
    protected GeneralLedgerService $gl;

    public function __construct(GeneralLedgerService $gl)
    {
        $this->gl = $gl;
    }

    /*
    |--------------------------------------------------------------------------
    | LIFECYCLE MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function submitForApproval(CapExProject $project): void
    {
        if ($project->status !== 'PLANNING') {
            throw new DomainException('Only planning projects can be submitted.');
        }

        $project->update(['status' => 'PENDING_APPROVAL']);
    }

    public function approveProject(CapExProject $project, int $approverId): void
    {
        if ($project->status !== 'PENDING_APPROVAL') {
            throw new DomainException('Project must be pending approval.');
        }

        $project->update([
            'status' => 'APPROVED',
            'approver_id' => $approverId,
            'approved_at' => now(),
        ]);
    }

    public function startProject(CapExProject $project): void
    {
        if ($project->status !== 'APPROVED') {
            throw new DomainException('Only approved projects can start.');
        }

        $project->update([
            'status' => 'IN_PROGRESS',
            'actual_start_date' => now(),
        ]);
    }

    public function putOnHold(CapExProject $project): void
    {
        if ($project->status !== 'IN_PROGRESS') {
            throw new DomainException('Only active projects can be paused.');
        }

        $project->update(['status' => 'ON_HOLD']);
    }

    public function resumeProject(CapExProject $project): void
    {
        if ($project->status !== 'ON_HOLD') {
            throw new DomainException('Project must be on hold.');
        }

        $project->update(['status' => 'IN_PROGRESS']);
    }

    public function cancelProject(CapExProject $project): void
    {
        if (in_array($project->status, ['COMPLETED'])) {
            throw new DomainException('Cannot cancel completed project.');
        }

        $project->update(['status' => 'CANCELLED']);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATION
    |--------------------------------------------------------------------------
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

            foreach ($data['budget_lines'] ?? [] as $line) {
                $project->lines()->create([
                    'line_number' => $this->getNextLineNumber($project),
                    'line_type' => $line['line_type'],
                    'description' => $line['description'],
                    'budget_amount' => $line['amount'],
                    'eligible_for_capitalization' => $this->isEligibleForCapitalization($line['line_type']),
                ]);
            }

            return $project;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | COST CAPTURE
    |--------------------------------------------------------------------------
    */

    public function captureProductionCosts(int $projectId, int $productionOrderId, ?array $options = []): void
    {
        $project = CapExProject::findOrFail($projectId);

        if (! in_array($project->status, ['IN_PROGRESS'])) {
            throw new DomainException('Project must be active to capture costs.');
        }

        $order = ProductionOrder::with(['components', 'routingLines.capacityEntries'])
            ->findOrFail($productionOrderId);

        DB::transaction(function () use ($project, $order, $options) {
            $captureMaterials = $options['capture_materials'] ?? $project->capitalize_materials;
            $captureLabor = $options['capture_labor'] ?? $project->capitalize_labor;
            $captureOverhead = $options['capture_overhead'] ?? $project->capitalize_overhead;

            foreach ($order->components as $component) {
                if (! $captureMaterials) {
                    continue;
                }

                $cost = $component->actual_quantity_consumed * $component->unit_cost;

                $this->createLineIfEligible($project, $cost, 'MATERIAL', [
                    'description' => "PO {$order->document_number} - {$component->item->description}",
                    'source_document_type' => 'PRODUCTION_ORDER',
                    'source_document_id' => $order->id,
                    'source_document_no' => $order->document_number,
                    'production_order_id' => $order->id,
                    'production_order_component_id' => $component->id,
                ]);
            }

            foreach ($order->routingLines as $routingLine) {
                foreach ($routingLine->capacityEntries as $entry) {
                    if ($captureLabor) {
                        $this->createLineIfEligible($project, $entry->direct_cost, 'LABOR', [
                            'source_document_type' => 'PRODUCTION_ORDER',
                            'source_document_id' => $order->id,
                            'source_document_no' => $order->document_number,
                            'production_order_id' => $order->id,
                            'capacity_ledger_entry_id' => $entry->id,
                        ]);
                    }

                    if ($captureOverhead) {
                        $this->createLineIfEligible($project, $entry->overhead_cost, 'OVERHEAD', [
                            'source_document_type' => 'PRODUCTION_ORDER',
                            'source_document_id' => $order->id,
                            'source_document_no' => $order->document_number,
                            'production_order_id' => $order->id,
                            'capacity_ledger_entry_id' => $entry->id,
                        ]);
                    }
                }
            }

            $this->recalculateProjectTotals($project);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CAPITALIZATION
    |--------------------------------------------------------------------------
    */

    public function capitalizeProject(int $projectId, array $assetData): FixedAsset
    {
        $project = CapExProject::with('lines')->findOrFail($projectId);

        $this->validateBeforeCapitalization($project);

        return DB::transaction(function () use ($project, $assetData) {
            $this->recalculateProjectTotals($project);

            $asset = $this->createFixedAsset($project, $assetData);

            $project->lines()
                ->where('eligible_for_capitalization', true)
                ->where('capitalized', false)
                ->update([
                    'capitalized' => true,
                    'capitalized_at' => now(),
                    'capitalized_by' => auth()->id(),
                ]);

            $project->update([
                'status' => 'COMPLETED',
                'capitalized_amount' => $project->actual_amount,
                'actual_end_date' => now(),
                'fixed_asset_id' => $asset->id,
            ]);

            $this->createCapitalizationJournalEntries($project, $asset);

            return $asset;
        });
    }

    public function capitalizeInterest(CapExProject $project, float $amount, \DateTime $periodEnd): void
    {
        if ($project->status !== 'IN_PROGRESS') {
            throw new DomainException('Interest can only be capitalized during active construction.');
        }

        $this->createLineIfEligible($project, $amount, 'INTEREST', [
            'description' => "Capitalized interest - {$periodEnd->format('M Y')}",
            'source_document_date' => $periodEnd,
        ]);

        $this->recalculateProjectTotals($project);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPENSE COSTS
    |--------------------------------------------------------------------------
    */

    public function expenseCosts(int $projectId, array $lineIds): void
    {
        $project = CapExProject::findOrFail($projectId);

        DB::transaction(function () use ($project, $lineIds) {
            $lines = $project->lines()->whereIn('id', $lineIds)->get();

            foreach ($lines as $line) {
                if ($line->capitalized) {
                    continue;
                }

                if ($line->actual_amount <= 0) {
                    continue;
                }

                // Mark processed
                $line->update([
                    'eligible_for_capitalization' => false,
                    'capitalized' => true,
                    'capitalized_at' => now(),
                ]);

                // Post GL
                $this->gl->post(
                    [
                        [
                            'account_id' => config('accounts.expense_manufacturing'),
                            'debit' => $line->actual_amount,
                            'credit' => 0,
                        ],
                        [
                            'account_id' => $project->wip_gl_account_id,
                            'debit' => 0,
                            'credit' => $line->actual_amount,
                        ],
                    ],
                    [
                        'document_number' => $project->project_number,
                        'description' => "CapEx Expense - Line {$line->id}",
                        'dimensions' => [
                            'project_id' => $project->id,
                        ],
                    ]
                );
            }

            $this->recalculateProjectTotals($project);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function recalculateProjectTotals(CapExProject $project): void
    {
        $project->update([
            'actual_amount' => $project->lines()->sum('actual_amount'),
            'committed_amount' => $project->lines()->sum('budget_amount'),
        ]);
    }

    protected function createLineIfEligible(
        CapExProject $project,
        float $amount,
        string $type,
        array $extra = []
    ): void {
        if ($amount <= 0) {
            return;
        }

        if ($project->capitalization_threshold && $amount < $project->capitalization_threshold) {
            return;
        }

        CapExProjectLine::create(array_merge([
            'capex_project_id' => $project->id,
            'line_number' => $this->getNextLineNumber($project),
            'line_type' => $type,
            'actual_amount' => $amount,
            'eligible_for_capitalization' => $this->isEligibleForCapitalization($type),
        ], $extra));
    }

    protected function validateBeforeCapitalization(CapExProject $project): void
    {
        if (! in_array($project->status, ['IN_PROGRESS', 'ON_HOLD'])) {
            throw new DomainException('Project must be active before capitalization.');
        }

        if ($project->lines()->where('eligible_for_capitalization', true)->where('capitalized', false)->count() === 0) {
            throw new DomainException('No eligible costs to capitalize.');
        }
    }

    protected function createFixedAsset(CapExProject $project, array $data): FixedAsset
    {
        $asset = FixedAsset::create([
            'code' => $data['asset_code'] ?? $project->project_number,
            'description' => $data['description'] ?? $project->description,
            'asset_type' => $data['asset_type'] ?? 'MACHINERY',
            'capex_project_id' => $project->id,
            'acquisition_date' => now(),
            'acquisition_cost' => $project->actual_amount,
            'net_book_value' => $project->actual_amount,
            'useful_life_years' => $data['useful_life_years'] ?? 10,
            'depreciation_method' => $data['depreciation_method'] ?? 'STRAIGHT_LINE',
            'salvage_value' => $data['salvage_value'] ?? 0,
            'annual_capacity_minutes' => $data['annual_capacity_minutes'] ?? null,
            'efficiency_percent' => $data['efficiency_percent'] ?? 100,
        ]);

        $asset->annual_depreciation_amount =
            ($asset->acquisition_cost - $asset->salvage_value) / $asset->useful_life_years;

        $asset->save();

        if (! empty($data['work_center_id'])) {
            $asset->workCenters()->attach($data['work_center_id'], [
                'allocation_percentage' => 100,
                'installation_date' => now(),
            ]);
        }

        return $asset;
    }

    protected function generateProjectNumber(): string
    {
        return 'CPX-'.now()->format('Y').'-'.str_pad(
            CapExProject::whereYear('created_at', now()->year)->count() + 1,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    protected function getNextLineNumber(CapExProject $project): int
    {
        return ($project->lines()->max('line_number') ?? 0) + 10000;
    }

    protected function isEligibleForCapitalization(string $type): bool
    {
        return in_array($type, ['MATERIAL', 'LABOR', 'OVERHEAD', 'INTEREST', 'EXTERNAL_SERVICE', 'TOOLING']);
    }

    protected function createCapitalizationJournalEntries(
        CapExProject $project,
        FixedAsset $asset
    ): void {
        $amount = $project->capitalized_amount;

        if ($amount <= 0) {
            throw new DomainException('Invalid capitalization amount.');
        }

        $this->gl->post(
            [
                [
                    'account_id' => $project->capex_gl_account_id, // Fixed Asset
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $project->wip_gl_account_id, // WIP Clearing
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
            [
                'document_number' => $asset->code,
                'description' => "CapEx Capitalization - {$project->project_number}",
                'dimensions' => [
                    'project_id' => $project->id,
                ],
            ]
        );
    }

    /**
     * Post WIP capture entries (debit WIP, credit production absorption)
     *
     * @deprecated This method is not currently used. Use captureProductionCosts() instead.
     */
    protected function postWipCapture(CapExProject $project, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $this->gl->post(
            [
                [
                    'account_id' => $project->wip_gl_account_id,
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => config('accounts.production_absorption'),
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
            [
                'document_number' => $project->project_number,
                'description' => 'WIP Cost Capture',
                'dimensions' => [
                    'project_id' => $project->id,
                ],
            ]
        );
    }
}

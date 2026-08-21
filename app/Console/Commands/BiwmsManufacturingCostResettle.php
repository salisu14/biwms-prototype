<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionCostSettlementStatus;
use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\User;
use App\Services\Manufacturing\ProductionCostSummaryService;
use App\Services\Manufacturing\ProductionOrderCostSettlementService;
use App\Support\DecimalMath;
use BackedEnum;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;
use UnitEnum;

#[Signature('biwms:manufacturing-cost-resettle {productionOrder : Production order ID or document number} {--dry-run : Report expected resettlement without mutating data} {--apply : Apply controlled production cost resettlement} {--user= : User ID to record as settlement actor when applying}')]
#[Description('Report or apply controlled production cost resettlement for one finished adjustment-required order.')]
class BiwmsManufacturingCostResettle extends Command
{
    public function __construct(
        private readonly ProductionCostSummaryService $summaryService,
        private readonly ProductionOrderCostSettlementService $settlementService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $order = $this->resolveProductionOrder((string) $this->argument('productionOrder'));

        if (! $order) {
            $this->error('Production order was not found or the filter matched multiple orders.');

            return self::FAILURE;
        }

        $orderStatus = $this->productionOrderStatus($order);
        $settlementStatus = $this->settlementStatus($order);
        $settlementClassification = $this->enumValue($order->cost_settlement_classification) ?? 'none';
        $summary = $this->summaryService->summarize($order);
        $positiveOutputEntries = $order->itemLedgerEntries()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT->value)
            ->where('quantity', '>', 0)
            ->count();
        $expectedDelta = round((float) $summary['total_accumulated_cost'] - (float) $summary['allocated_output_cost'], 4);

        $this->info('BIWMS Manufacturing Cost Resettlement');
        $this->line($apply ? 'Mode: apply. Controlled resettlement will run.' : 'Mode: dry-run. No data was changed.');
        $this->line("Production Order: {$order->document_number}");
        $this->line('Status: '.$orderStatus?->value);
        $this->line('Settlement Status: '.$settlementStatus?->value);
        $this->line('Settlement Classification: '.$settlementClassification);
        $this->line('Positive Output Entries: '.$positiveOutputEntries);
        $this->line('Current Total Accumulated Cost: '.$this->formatAmount((float) $summary['total_accumulated_cost']));
        $this->line('Current Allocated Output Cost: '.$this->formatAmount((float) $summary['allocated_output_cost']));
        $this->line('Expected Resettlement Delta: '.$this->formatAmount($expectedDelta));
        $this->line('Expected Allocated Output After Resettlement: '.$this->formatAmount((float) $summary['total_accumulated_cost']));
        $this->line('Dry Run Mutates Data: no');

        if ($orderStatus !== ProductionOrderStatus::FINISHED || $settlementStatus !== ProductionCostSettlementStatus::AdjustmentRequired) {
            $this->error('Only FINISHED production orders with adjustment_required settlement status can be resettled by this command.');

            return self::FAILURE;
        }

        if ($positiveOutputEntries !== 1) {
            $this->error('Controlled resettlement requires exactly one positive Output Item Ledger Entry.');

            return self::FAILURE;
        }

        if (! $apply) {
            return self::SUCCESS;
        }

        $userId = $this->resolveSettlementUserId($order);
        if (! $userId) {
            $this->error('No settlement user could be resolved. Pass --user={id} for apply mode.');

            return self::FAILURE;
        }

        try {
            $result = $this->settlementService->settle($order->fresh(), $userId);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $afterSummary = $this->summaryService->summarize($order->fresh());

        $this->line('Apply Result: '.json_encode([
            'settled' => $result['settled'] ?? false,
            'idempotent' => $result['idempotent'] ?? false,
            'status' => $result['status'] ?? null,
            'classification' => $result['classification'] ?? null,
        ], JSON_THROW_ON_ERROR));
        $this->line('Allocated Output After Resettlement: '.$this->formatAmount((float) $afterSummary['allocated_output_cost']));
        $this->line('Open WIP After Resettlement: '.$this->formatAmount((float) $afterSummary['unallocated_cost']));

        return self::SUCCESS;
    }

    private function resolveProductionOrder(string $filter): ?ProductionOrder
    {
        $matches = ProductionOrder::query()
            ->where('document_number', $filter)
            ->when(is_numeric($filter), fn ($query) => $query->orWhere('id', (int) $filter))
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    private function productionOrderStatus(ProductionOrder $order): ?ProductionOrderStatus
    {
        return $order->status instanceof ProductionOrderStatus
            ? $order->status
            : ProductionOrderStatus::tryFrom((string) $order->status);
    }

    private function settlementStatus(ProductionOrder $order): ?ProductionCostSettlementStatus
    {
        return $order->cost_settlement_status instanceof ProductionCostSettlementStatus
            ? $order->cost_settlement_status
            : ProductionCostSettlementStatus::tryFrom((string) $order->cost_settlement_status);
    }

    private function resolveSettlementUserId(ProductionOrder $order): ?int
    {
        $option = $this->option('user');
        if ($option !== null && $option !== '') {
            $userId = User::query()->whereKey((int) $option)->value('id');

            return $userId ? (int) $userId : null;
        }

        foreach ([$order->cost_settled_by, $order->posted_by, $order->created_by] as $candidate) {
            if ($candidate && User::query()->whereKey((int) $candidate)->exists()) {
                return (int) $candidate;
            }
        }

        $userId = User::query()->value('id');

        return $userId ? (int) $userId : null;
    }

    private function formatAmount(float $amount): string
    {
        return DecimalMath::amount($amount);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return filled($value) ? (string) $value : null;
    }
}

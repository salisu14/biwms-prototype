<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\ItemLedgerEntryType;
use App\Enums\SourceType;
use App\Models\CapacityLedgerEntry as InventoryCapacityLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralPostingSetup;
use App\Models\InventoryPostingSetup;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\PostingTransaction;
use App\Models\ValueEntry;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use UnitEnum;

class ValueEntryAccountingOrchestrator
{
    public function __construct(
        private readonly GeneralLedgerService $generalLedgerService,
    ) {}

    public function post(ValueEntry $valueEntry): ?PostingTransaction
    {
        return DB::transaction(function () use ($valueEntry): ?PostingTransaction {
            /** @var ValueEntry $lockedValueEntry */
            $lockedValueEntry = ValueEntry::query()
                ->with(['itemLedgerEntry.item.inventoryPostingGroup.inventoryPostingSetups.inventoryAccount', 'itemLedgerEntry.location'])
                ->lockForUpdate()
                ->findOrFail($valueEntry->id);

            if ($lockedValueEntry->gl_posted) {
                return $lockedValueEntry->postingTransaction;
            }

            if ($lockedValueEntry->expected_cost && ! config('accounts.post_expected_inventory_cost_to_gl', false)) {
                return null;
            }

            $amount = $this->amountToPost($lockedValueEntry);
            if ($amount <= 0.0) {
                return null;
            }

            [$debitAccount, $creditAccount] = $this->resolveAccounts($lockedValueEntry);
            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $debitAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $this->description($lockedValueEntry, 'Debit'),
                    'source_type' => SourceType::ITEM->value,
                    'source_number' => $lockedValueEntry->item_no,
                    'cost_component' => $lockedValueEntry->cost_component ?? $this->defaultCostComponent($lockedValueEntry),
                    'item_ledger_entry_id' => $lockedValueEntry->itemLedgerEntry?->id,
                ],
                [
                    'account_id' => $creditAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $this->description($lockedValueEntry, 'Credit'),
                    'source_type' => SourceType::ITEM->value,
                    'source_number' => $lockedValueEntry->item_no,
                    'cost_component' => $lockedValueEntry->cost_component ?? $this->defaultCostComponent($lockedValueEntry),
                    'item_ledger_entry_id' => $lockedValueEntry->itemLedgerEntry?->id,
                ],
            ], [
                'business_id' => $lockedValueEntry->business_id,
                'posting_date' => $lockedValueEntry->posting_date,
                'document_date' => $lockedValueEntry->valuation_date ?? $lockedValueEntry->posting_date,
                'document_type' => $lockedValueEntry->document_type ?? 'VALUE_ENTRY',
                'document_number' => $lockedValueEntry->document_no ?? (string) $lockedValueEntry->entry_no,
                'source_module' => $lockedValueEntry->source_module ?? 'inventory',
                'source_type' => SourceType::ITEM->value,
                'source_id' => $lockedValueEntry->source_id,
                'source_number' => $lockedValueEntry->source_number ?? $lockedValueEntry->item_no,
                'description' => $this->description($lockedValueEntry),
                'idempotency_key' => $this->idempotencyKey($lockedValueEntry),
                'transaction_key' => 'value-entry:'.$lockedValueEntry->entry_no,
                'dimensions' => $lockedValueEntry->shortcut_dimension_codes ?? $lockedValueEntry->dimension_set_id ?? [],
                'actor_id' => is_numeric($lockedValueEntry->user_id) ? (int) $lockedValueEntry->user_id : null,
            ]);

            $lockedValueEntry->forceFill([
                'gl_posted' => true,
                'gl_posting_date' => now()->toDateString(),
                'gl_posted_at' => now(),
                'posting_transaction_id' => $transaction->id,
                'gl_entry_no' => $transaction->glEntries()->orderBy('id')->value('id'),
                'gl_account_no' => $debitAccount->account_number,
                'balancing_account_no' => $creditAccount->account_number,
                'idempotency_key' => $lockedValueEntry->idempotency_key ?: $this->idempotencyKey($lockedValueEntry),
                'accounting_metadata' => array_merge($lockedValueEntry->accounting_metadata ?? [], [
                    'phase_1b_owner' => 'value_entry',
                    'debit_account_id' => $debitAccount->id,
                    'credit_account_id' => $creditAccount->id,
                    'amount' => $amount,
                ]),
            ])->save();

            return $transaction;
        });
    }

    public function postForItemLedgerEntry(ItemLedgerEntry $itemLedgerEntry): ?PostingTransaction
    {
        $valueEntry = ValueEntry::query()
            ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
            ->where('document_no', $itemLedgerEntry->document_number)
            ->where('document_line_no', $itemLedgerEntry->document_line_number)
            ->first();

        if (! $valueEntry) {
            $valueEntry = app(ValueEntryService::class)->ensureForItemLedgerEntry($itemLedgerEntry);
        }

        return $valueEntry ? $this->post($valueEntry) : null;
    }

    public function postForCapacityLedgerEntry(CapacityLedgerEntry|InventoryCapacityLedgerEntry $capacityLedgerEntry): array
    {
        app(ValueEntryService::class)->ensureForCapacityLedgerEntry($capacityLedgerEntry);

        return ValueEntry::query()
            ->whereIn('source_type', [CapacityLedgerEntry::class, InventoryCapacityLedgerEntry::class])
            ->where('source_id', $capacityLedgerEntry->id)
            ->get()
            ->map(fn (ValueEntry $valueEntry): ?PostingTransaction => $this->post($valueEntry))
            ->filter()
            ->all();
    }

    /**
     * @return array{0: ChartOfAccount, 1: ChartOfAccount}
     */
    private function resolveAccounts(ValueEntry $valueEntry): array
    {
        $type = $this->normalizedItemLedgerEntryType($valueEntry);
        $documentType = strtoupper((string) $valueEntry->document_type);

        return match ($type) {
            'SALE' => str_contains($documentType, 'CREDIT_MEMO') || (float) $valueEntry->quantity > 0
                ? [$this->inventoryAccount($valueEntry), $this->cogsAccount($valueEntry, true)]
                : [$this->cogsAccount($valueEntry), $this->inventoryAccount($valueEntry)],
            'PURCHASE' => str_contains($documentType, 'CREDIT_MEMO') || (float) $valueEntry->quantity < 0
                ? [$this->purchaseOffsetAccount($valueEntry, true), $this->inventoryAccount($valueEntry)]
                : [$this->inventoryAccount($valueEntry), $this->purchaseOffsetAccount($valueEntry)],
            'POSITIVE_ADJUSTMENT' => [$this->inventoryAccount($valueEntry), $this->inventoryAdjustmentAccount($valueEntry)],
            'NEGATIVE_ADJUSTMENT' => [$this->inventoryAdjustmentAccount($valueEntry), $this->inventoryAccount($valueEntry)],
            'CONSUMPTION' => [$this->wipAccount($valueEntry), $this->inventoryAccount($valueEntry)],
            'OUTPUT' => [$this->inventoryAccount($valueEntry), $this->wipAccount($valueEntry)],
            'TRANSFER' => (float) $valueEntry->quantity < 0
                ? [$this->inventoryInTransitAccount($valueEntry), $this->inventoryAccount($valueEntry)]
                : [$this->inventoryAccount($valueEntry), $this->inventoryInTransitAccount($valueEntry)],
            'CAPACITY' => [$this->wipAccount($valueEntry), $this->directCostAppliedAccount($valueEntry)],
            'OVERHEAD' => [$this->wipAccount($valueEntry), $this->overheadAppliedAccount($valueEntry)],
            default => throw new RuntimeException("Unsupported value entry type {$type} for G/L posting."),
        };
    }

    private function amountToPost(ValueEntry $valueEntry): float
    {
        $amount = (float) ($valueEntry->expected_cost ? $valueEntry->cost_amount_expected : $valueEntry->cost_amount_actual);

        return abs($amount);
    }

    private function inventoryAccount(ValueEntry $valueEntry): ChartOfAccount
    {
        $itemLedgerEntry = $this->itemLedgerEntry($valueEntry);
        $setup = InventoryPostingSetup::getFor((int) $itemLedgerEntry->inventory_posting_group_id, $itemLedgerEntry->location_id);

        if (! $setup?->inventoryAccount) {
            throw new RuntimeException("Inventory account missing for value entry {$valueEntry->entry_no}.");
        }

        return $setup->inventoryAccount;
    }

    private function inventoryInTransitAccount(ValueEntry $valueEntry): ChartOfAccount
    {
        $itemLedgerEntry = $this->itemLedgerEntry($valueEntry);
        $setup = InventoryPostingSetup::getFor((int) $itemLedgerEntry->inventory_posting_group_id, $itemLedgerEntry->location_id);

        if (! $setup?->inventoryInTransitAccount) {
            throw new RuntimeException("Inventory in-transit account missing for value entry {$valueEntry->entry_no}.");
        }

        return $setup->inventoryInTransitAccount;
    }

    private function wipAccount(ValueEntry $valueEntry): ChartOfAccount
    {
        if (in_array($this->normalizedItemLedgerEntryType($valueEntry), ['CAPACITY', 'OVERHEAD'], true)) {
            $productionOrder = $this->productionOrder($valueEntry);
            $location = $this->locationForProductionOrder($productionOrder);
            $setup = InventoryPostingSetup::getFor((int) $productionOrder->inventory_posting_group_id, $location?->id);

            if (! $setup?->wipAccount) {
                throw new RuntimeException("WIP account missing for value entry {$valueEntry->entry_no}.");
            }

            return $setup->wipAccount;
        }

        $itemLedgerEntry = $this->itemLedgerEntry($valueEntry);
        $setup = InventoryPostingSetup::getFor((int) $itemLedgerEntry->inventory_posting_group_id, $itemLedgerEntry->location_id);

        if (! $setup?->wipAccount) {
            throw new RuntimeException("WIP account missing for value entry {$valueEntry->entry_no}.");
        }

        return $setup->wipAccount;
    }

    private function cogsAccount(ValueEntry $valueEntry, bool $creditMemo = false): ChartOfAccount
    {
        $setup = $this->generalPostingSetup($valueEntry);
        $account = $creditMemo ? ($setup->cogsCreditMemoAccount ?? $setup->cogsAccount) : $setup->cogsAccount;

        if (! $account) {
            throw new RuntimeException("COGS account missing for value entry {$valueEntry->entry_no}.");
        }

        return $account;
    }

    private function inventoryAdjustmentAccount(ValueEntry $valueEntry): ChartOfAccount
    {
        $setup = $this->generalPostingSetup($valueEntry);

        if (! $setup->inventoryAdjAccount) {
            throw new RuntimeException("Inventory adjustment account missing for value entry {$valueEntry->entry_no}.");
        }

        return $setup->inventoryAdjAccount;
    }

    private function purchaseOffsetAccount(ValueEntry $valueEntry, bool $creditMemo = false): ChartOfAccount
    {
        $setup = $this->generalPostingSetup($valueEntry);
        $account = $creditMemo ? ($setup->purchaseCreditMemoAccount ?? $setup->purchaseAccount) : $setup->purchaseAccount;

        if (! $account) {
            throw new RuntimeException("Purchase offset account missing for value entry {$valueEntry->entry_no}.");
        }

        return $account;
    }

    private function directCostAppliedAccount(ValueEntry $valueEntry): ChartOfAccount
    {
        $setup = $this->generalPostingSetup($valueEntry);

        if (! $setup->directCostAppliedAccount) {
            throw new RuntimeException("Direct cost applied account missing for value entry {$valueEntry->entry_no}.");
        }

        return $setup->directCostAppliedAccount;
    }

    private function overheadAppliedAccount(ValueEntry $valueEntry): ChartOfAccount
    {
        $setup = $this->generalPostingSetup($valueEntry);

        if (! $setup->overheadAppliedAccount) {
            throw new RuntimeException("Overhead applied account missing for value entry {$valueEntry->entry_no}.");
        }

        return $setup->overheadAppliedAccount;
    }

    private function generalPostingSetup(ValueEntry $valueEntry): GeneralPostingSetup
    {
        if (in_array($this->normalizedItemLedgerEntryType($valueEntry), ['CAPACITY', 'OVERHEAD'], true)) {
            $setup = $this->productionOrder($valueEntry)->getPostingSetup();

            if (! $setup) {
                throw new RuntimeException("General posting setup missing for value entry {$valueEntry->entry_no}.");
            }

            return $setup;
        }

        $itemLedgerEntry = $this->itemLedgerEntry($valueEntry);

        $setup = GeneralPostingSetup::query()
            ->where('general_business_posting_group_id', $itemLedgerEntry->general_business_posting_group_id)
            ->where('general_product_posting_group_id', $itemLedgerEntry->general_product_posting_group_id)
            ->where('blocked', false)
            ->first();

        if (! $setup && ! $itemLedgerEntry->general_business_posting_group_id) {
            $candidateSetups = GeneralPostingSetup::query()
                ->where('general_product_posting_group_id', $itemLedgerEntry->general_product_posting_group_id)
                ->where('blocked', false)
                ->limit(2)
                ->get();

            if ($candidateSetups->count() === 1) {
                $setup = $candidateSetups->first();
            }
        }

        if (! $setup) {
            throw new RuntimeException("General posting setup missing for value entry {$valueEntry->entry_no}.");
        }

        return $setup;
    }

    private function itemLedgerEntry(ValueEntry $valueEntry): ItemLedgerEntry
    {
        $itemLedgerEntry = $valueEntry->itemLedgerEntry;

        if (! $itemLedgerEntry) {
            throw new RuntimeException("Item ledger entry missing for value entry {$valueEntry->entry_no}.");
        }

        return $itemLedgerEntry;
    }

    private function productionOrder(ValueEntry $valueEntry): ProductionOrder
    {
        $productionOrder = $valueEntry->productionOrder;

        if ($productionOrder) {
            return $productionOrder;
        }

        if (in_array($valueEntry->source_type, [CapacityLedgerEntry::class, InventoryCapacityLedgerEntry::class], true) && $valueEntry->source_id) {
            $capacityLedgerEntryClass = $valueEntry->source_type;
            $capacityLedgerEntry = $capacityLedgerEntryClass::query()
                ->with('productionOrder')
                ->find($valueEntry->source_id);

            if ($capacityLedgerEntry?->productionOrder) {
                return $capacityLedgerEntry->productionOrder;
            }
        }

        throw new RuntimeException("Production order missing for value entry {$valueEntry->entry_no}.");
    }

    private function locationForProductionOrder(ProductionOrder $productionOrder): ?Location
    {
        return Location::query()
            ->where('code', $productionOrder->location_code)
            ->first();
    }

    private function idempotencyKey(ValueEntry $valueEntry): string
    {
        return hash('sha256', 'value-entry:'.$valueEntry->entry_no.':'.$valueEntry->document_no);
    }

    private function description(ValueEntry $valueEntry, ?string $side = null): string
    {
        $prefix = $side ? "{$side} " : '';

        return $prefix.'Value Entry '.$valueEntry->entry_no.' '.$this->normalizedItemLedgerEntryType($valueEntry).' '.$valueEntry->item_no;
    }

    private function defaultCostComponent(ValueEntry $valueEntry): string
    {
        return strtolower($this->normalizedItemLedgerEntryType($valueEntry));
    }

    private function normalizedItemLedgerEntryType(ValueEntry $valueEntry): string
    {
        $type = $valueEntry->item_ledger_entry_type;
        $type = $type instanceof UnitEnum ? $type->value : (string) $type;
        $type = strtoupper($type);

        return match ($type) {
            '1', strtoupper(ItemLedgerEntryType::PURCHASE->value) => 'PURCHASE',
            '2', strtoupper(ItemLedgerEntryType::SALE->value), 'SALES_RETURN' => 'SALE',
            '3', strtoupper(ItemLedgerEntryType::POSITIVE_ADJUSTMENT->value), 'POSITIVE ADJMT.', 'POSITIVE_ADJ' => 'POSITIVE_ADJUSTMENT',
            '4', strtoupper(ItemLedgerEntryType::NEGATIVE_ADJUSTMENT->value), 'NEGATIVE ADJMT.', 'NEGATIVE_ADJ' => 'NEGATIVE_ADJUSTMENT',
            '5', strtoupper(ItemLedgerEntryType::TRANSFER->value) => 'TRANSFER',
            '6', strtoupper(ItemLedgerEntryType::CONSUMPTION->value) => 'CONSUMPTION',
            '7', strtoupper(ItemLedgerEntryType::OUTPUT->value) => 'OUTPUT',
            '8' => 'CAPACITY',
            '10' => 'OVERHEAD',
            default => $type,
        };
    }
}

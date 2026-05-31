<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\GeneralPostingSetup;
use App\Models\GLEntry;
use App\Models\InventoryPostingSetup;
use App\Models\ValueEntry;
use Exception;

class ValueEntryAccountingService
{
    public function determineGLAccount(ValueEntry $valueEntry): ?string
    {
        $item = $valueEntry->item;
        $location = $valueEntry->location_code;

        $setup = InventoryPostingSetup::query()
            ->where('location_code', $location)
            ->where('inventory_posting_group', $item?->inventory_posting_group_code)
            ->first();

        if (! $setup) {
            return null;
        }

        return match ($valueEntry->item_ledger_entry_type) {
            'PURCHASE' => $setup->inventory_account,
            'SALE' => $setup->cogs_account,
            'POSITIVE_ADJUSTMENT', 'NEGATIVE_ADJUSTMENT' => $setup->inventory_adj_account,
            'TRANSFER' => $setup->inventory_account,
            'CONSUMPTION' => $setup->wip_account,
            'OUTPUT' => $setup->inventory_account,
            'CAPACITY' => $setup->wip_account,
            'OVERHEAD' => $setup->wip_account,
            default => $setup->inventory_account,
        };
    }

    public function determineBalancingAccount(ValueEntry $valueEntry): ?string
    {
        return match ($valueEntry->item_ledger_entry_type) {
            'PURCHASE' => $valueEntry->vendor?->payables_account,
            'SALE' => $this->getSalesAccount($valueEntry),
            'CONSUMPTION' => $this->getAppliedAccount($valueEntry),
            'OUTPUT' => $this->getWipAccount($valueEntry),
            'CAPACITY' => $this->getDirectCostAppliedAccount(),
            'OVERHEAD' => $this->getOverheadAppliedAccount(),
            default => null,
        };
    }

    public function postToGL(ValueEntry $valueEntry): GLEntry
    {
        if ($valueEntry->gl_posted) {
            throw new Exception("Value Entry {$valueEntry->entry_no} already posted to G/L");
        }

        $debitAccount = $this->determineGLAccount($valueEntry);
        $creditAccount = $this->determineBalancingAccount($valueEntry);
        $amount = abs((float) $valueEntry->cost_amount_actual);

        $isDebit = in_array($valueEntry->item_ledger_entry_type, [
            'PURCHASE', 'POSITIVE_ADJUSTMENT', 'CONSUMPTION',
            'CAPACITY', 'OVERHEAD', 'TRANSFER_IN',
        ], true);

        $glEntry = GLEntry::create([
            'posting_date' => $valueEntry->posting_date,
            'document_type' => $valueEntry->document_type ?? 'PRODUCTION',
            'document_no' => $valueEntry->document_no ?? $valueEntry->source_no,
            'description' => $this->getGLDescription($valueEntry),
            'account_no' => $isDebit ? $debitAccount : $creditAccount,
            'debit_amount' => $isDebit ? $amount : 0,
            'credit_amount' => $isDebit ? 0 : $amount,
            'source_type' => 'VALUE_ENTRY',
            'source_no' => (string) $valueEntry->entry_no,
        ]);

        GLEntry::create([
            'posting_date' => $valueEntry->posting_date,
            'document_type' => $valueEntry->document_type ?? 'PRODUCTION',
            'document_no' => $valueEntry->document_no ?? $valueEntry->source_no,
            'description' => $this->getGLDescription($valueEntry).' (Balancing)',
            'account_no' => $isDebit ? $creditAccount : $debitAccount,
            'debit_amount' => $isDebit ? 0 : $amount,
            'credit_amount' => $isDebit ? $amount : 0,
            'source_type' => 'VALUE_ENTRY',
            'source_no' => (string) $valueEntry->entry_no,
        ]);

        $valueEntry->update([
            'gl_posted' => true,
            'gl_posting_date' => now(),
            'gl_entry_no' => $glEntry->id,
            'gl_account_no' => $debitAccount,
            'balancing_account_no' => $creditAccount,
        ]);

        return $glEntry;
    }

    public function reverse(ValueEntry $valueEntry, mixed $postingDate = null): ValueEntry
    {
        $reversal = $valueEntry->replicate();
        $reversal->entry_no = (ValueEntry::max('entry_no') ?? 0) + 1;
        $reversal->quantity = -(float) $valueEntry->quantity;
        $reversal->invoiced_quantity = -(float) $valueEntry->invoiced_quantity;
        $reversal->cost_amount_actual = -(float) $valueEntry->cost_amount_actual;
        $reversal->cost_amount_expected = -(float) $valueEntry->cost_amount_expected;
        $reversal->direct_cost_amount = -(float) $valueEntry->direct_cost_amount;
        $reversal->indirect_cost_amount = -(float) $valueEntry->indirect_cost_amount;
        $reversal->overhead_amount = -(float) $valueEntry->overhead_amount;
        $reversal->posting_date = $postingDate ?? now();
        $reversal->description = 'Reversal of Entry '.$valueEntry->entry_no;
        $reversal->original_entry_no = $valueEntry->id;
        $reversal->entry_type = 'REVERSAL';
        $reversal->gl_posted = false;
        $reversal->cost_adjusted = false;
        $reversal->save();

        return $reversal;
    }

    public function adjustCost(ValueEntry $valueEntry, float $newCostAmount, string $reason = ''): ValueEntry
    {
        $adjustment = $valueEntry->replicate();
        $adjustment->entry_no = (ValueEntry::max('entry_no') ?? 0) + 1;
        $adjustment->cost_amount_actual = $newCostAmount - (float) $valueEntry->cost_amount_actual;
        $adjustment->cost_amount_expected = 0;
        $adjustment->entry_type = 'REVALUATION';
        $adjustment->original_entry_no = $valueEntry->id;
        $adjustment->description = "Cost Adjustment: {$reason}";
        $adjustment->adjustment_entry_no = $valueEntry->id;
        $adjustment->gl_posted = false;
        $adjustment->save();

        $valueEntry->update([
            'cost_adjusted' => true,
            'cost_adjustment_date' => now(),
            'cost_adjustment_entry_no' => $adjustment->id,
        ]);

        return $adjustment;
    }

    private function getSalesAccount(ValueEntry $valueEntry): ?string
    {
        $setup = GeneralPostingSetup::query()
            ->where('gen_bus_posting_group', $valueEntry->customer?->gen_bus_posting_group_code)
            ->where('gen_prod_posting_group', $valueEntry->item?->gen_prod_posting_group_code)
            ->first();

        return $setup?->cogs_account;
    }

    private function getAppliedAccount(ValueEntry $valueEntry): ?string
    {
        $setup = GeneralPostingSetup::query()
            ->where('gen_bus_posting_group', 'MANUFACTURING')
            ->where('gen_prod_posting_group', $valueEntry->item?->gen_prod_posting_group_code)
            ->first();

        return $setup?->direct_cost_applied_account;
    }

    private function getWipAccount(ValueEntry $valueEntry): ?string
    {
        $setup = InventoryPostingSetup::query()
            ->where('location_code', $valueEntry->location_code)
            ->where('inventory_posting_group', 'WIP-PROD')
            ->first();

        return $setup?->wip_account;
    }

    private function getDirectCostAppliedAccount(): ?string
    {
        $setup = GeneralPostingSetup::query()
            ->where('gen_bus_posting_group', 'MANUFACTURING')
            ->where('gen_prod_posting_group', 'CAPACITY')
            ->first();

        return $setup?->direct_cost_applied_account;
    }

    private function getOverheadAppliedAccount(): ?string
    {
        $setup = GeneralPostingSetup::query()
            ->where('gen_bus_posting_group', 'MANUFACTURING')
            ->where('gen_prod_posting_group', 'FIN-GOODS')
            ->first();

        return $setup?->overhead_applied_account;
    }

    private function getGLDescription(ValueEntry $valueEntry): string
    {
        return match ($valueEntry->item_ledger_entry_type) {
            'CONSUMPTION' => "Consumption: {$valueEntry->item_no} -> PO {$valueEntry->production_order_no}",
            'OUTPUT' => "Output: PO {$valueEntry->production_order_no} -> {$valueEntry->item_no}",
            'CAPACITY' => "Capacity: {$valueEntry->capacity_type} {$valueEntry->capacity_no} -> PO {$valueEntry->production_order_no}",
            'OVERHEAD' => "Overhead: Applied to PO {$valueEntry->production_order_no}",
            default => "{$valueEntry->item_ledger_entry_type}: {$valueEntry->item_no}",
        };
    }
}

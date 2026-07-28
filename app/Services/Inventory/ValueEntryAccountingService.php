<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\GlEntry;
use App\Models\ValueEntry;
use RuntimeException;

class ValueEntryAccountingService
{
    public function __construct(
        private readonly ValueEntryAccountingOrchestrator $orchestrator,
    ) {}

    public function determineGLAccount(ValueEntry $valueEntry): ?string
    {
        return $valueEntry->gl_account_no;
    }

    public function determineBalancingAccount(ValueEntry $valueEntry): ?string
    {
        return $valueEntry->balancing_account_no;
    }

    public function postToGL(ValueEntry $valueEntry): GlEntry
    {
        $transaction = $this->orchestrator->post($valueEntry);

        $glEntry = $transaction?->glEntries()->orderBy('id')->first()
            ?? $valueEntry->fresh()?->glEntry;

        if (! $glEntry) {
            throw new RuntimeException("Value Entry {$valueEntry->entry_no} did not produce a G/L entry.");
        }

        return $glEntry;
    }

    public function reverse(ValueEntry $valueEntry, mixed $postingDate = null): ValueEntry
    {
        $reversal = $valueEntry->replicate();
        $reversal->entry_no = (ValueEntry::max('entry_no') ?? 0) + 1;
        $reversal->quantity = -(float) $valueEntry->quantity;
        $reversal->invoiced_quantity = -(float) $valueEntry->invoiced_quantity;
        $reversal->valued_quantity = -(float) ($valueEntry->valued_quantity ?? $valueEntry->quantity);
        $reversal->remaining_quantity = -(float) ($valueEntry->remaining_quantity ?? 0);
        $reversal->cost_amount_actual = -(float) $valueEntry->cost_amount_actual;
        $reversal->cost_amount_expected = -(float) $valueEntry->cost_amount_expected;
        $reversal->direct_cost_amount = -(float) $valueEntry->direct_cost_amount;
        $reversal->indirect_cost_amount = -(float) $valueEntry->indirect_cost_amount;
        $reversal->overhead_amount = -(float) $valueEntry->overhead_amount;
        $reversal->posting_date = $postingDate ?? now();
        $reversal->description = 'Reversal of Entry '.$valueEntry->entry_no;
        $reversal->original_entry_no = $valueEntry->id;
        $reversal->reversal_of_value_entry_id = $valueEntry->id;
        $reversal->entry_type = 'REVERSAL';
        $reversal->value_entry_state = 'reversal';
        $reversal->gl_posted = false;
        $reversal->posting_transaction_id = null;
        $reversal->gl_entry_no = null;
        $reversal->gl_posted_at = null;
        $reversal->cost_adjusted = false;
        $reversal->idempotency_key = null;
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
        $adjustment->value_entry_state = 'adjustment';
        $adjustment->original_entry_no = $valueEntry->id;
        $adjustment->description = "Cost Adjustment: {$reason}";
        $adjustment->adjustment_entry_no = $valueEntry->id;
        $adjustment->gl_posted = false;
        $adjustment->posting_transaction_id = null;
        $adjustment->gl_entry_no = null;
        $adjustment->gl_posted_at = null;
        $adjustment->idempotency_key = null;
        $adjustment->save();

        $valueEntry->update([
            'cost_adjusted' => true,
            'cost_adjustment_date' => now(),
            'cost_adjustment_entry_no' => $adjustment->id,
        ]);

        return $adjustment;
    }
}

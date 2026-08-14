<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\ValueEntry;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpectedCostClearingService
{
    public function __construct(
        private readonly ValueEntryAccountingOrchestrator $accountingOrchestrator,
    ) {}

    public function clearForActualPurchaseInvoice(
        ValueEntry $expectedEntry,
        PurchaseInvoice $invoice,
        PurchaseInvoiceLine $line,
        float $quantityBase,
        float $actualCostAmount
    ): ?ValueEntry {
        if (! config('accounts.post_expected_inventory_cost_to_gl', false)) {
            return null;
        }

        if (! $expectedEntry->gl_posted) {
            return null;
        }

        if ($quantityBase <= 0.0) {
            throw new RuntimeException('Expected-cost clearing quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($expectedEntry, $invoice, $line, $quantityBase, $actualCostAmount): ?ValueEntry {
            /** @var ValueEntry $lockedExpected */
            $lockedExpected = ValueEntry::query()
                ->lockForUpdate()
                ->findOrFail($expectedEntry->id);

            $idempotencyKey = $this->idempotencyKey($lockedExpected, $invoice, $line, $quantityBase);
            $existing = ValueEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $expectedQuantity = abs((float) ($lockedExpected->valued_quantity ?: $lockedExpected->quantity));
            $expectedAmount = abs((float) $lockedExpected->cost_amount_expected);
            $amountToClear = $expectedQuantity > 0.0
                ? round($expectedAmount * ($quantityBase / $expectedQuantity), 4)
                : $actualCostAmount;

            $clearingEntry = ValueEntry::query()->create([
                'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
                'item_ledger_entry_no' => $lockedExpected->item_ledger_entry_no,
                'item_ledger_entry_type' => $lockedExpected->item_ledger_entry_type,
                'item_no' => $lockedExpected->item_no,
                'location_code' => $lockedExpected->location_code,
                'posting_date' => $invoice->posting_date,
                'valuation_date' => $invoice->posting_date,
                'document_type' => 'EXPECTED_COST_CLEARING',
                'document_no' => $invoice->document_number,
                'document_line_no' => $line->line_number ?? $line->id,
                'description' => 'Clear expected purchase receipt cost',
                'quantity' => DecimalMath::quantity(-$quantityBase),
                'invoiced_quantity' => DecimalMath::quantity($quantityBase),
                'valued_quantity' => DecimalMath::quantity(-$quantityBase),
                'remaining_quantity' => 0,
                'costing_method' => $lockedExpected->costing_method,
                'cost_component' => 'expected_cost_clearing',
                'value_entry_state' => 'clearing',
                'cost_amount_actual' => 0,
                'cost_amount_expected' => DecimalMath::amount(-$amountToClear),
                'cost_amount_actual_acy' => 0,
                'cost_amount_expected_acy' => DecimalMath::amount(-$amountToClear),
                'unit_cost' => DecimalMath::unitCost($quantityBase > 0 ? $amountToClear / $quantityBase : 0),
                'unit_cost_acy' => DecimalMath::unitCost($quantityBase > 0 ? $amountToClear / $quantityBase : 0),
                'source_type' => PurchaseInvoice::class,
                'source_module' => 'purchases',
                'source_id' => $invoice->id,
                'source_number' => $invoice->document_number,
                'source_no' => (string) $invoice->id,
                'source_line_no' => $line->line_number ?? $line->id,
                'purchase_order_no' => $invoice->order_number,
                'purchase_order_line_no' => $line->po_line_number,
                'vendor_no' => (string) ($invoice->vendor?->vendor_number ?? $invoice->vendor_id),
                'expected_cost' => true,
                'reversal_of_value_entry_id' => $lockedExpected->id,
                'original_entry_no' => $lockedExpected->id,
                'idempotency_key' => $idempotencyKey,
                'accounting_metadata' => [
                    'phase_1c_expected_cost_clearing' => true,
                    'expected_value_entry_id' => $lockedExpected->id,
                    'actual_invoice_document_no' => $invoice->document_number,
                    'cleared_quantity_base' => $quantityBase,
                    'cleared_expected_amount' => $amountToClear,
                ],
                'user_id' => auth()->id() ? (string) auth()->id() : null,
            ]);

            $this->accountingOrchestrator->post($clearingEntry);

            return $clearingEntry->fresh();
        });
    }

    public function clearForActualManufacturingCost(
        ValueEntry $expectedEntry,
        ValueEntry $actualEntry,
        float $quantityBase,
        float $amountToClear,
        ?int $userId = null
    ): ?ValueEntry {
        if ($quantityBase <= 0.0) {
            throw new RuntimeException('Expected manufacturing cost clearing quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($expectedEntry, $actualEntry, $quantityBase, $amountToClear, $userId): ?ValueEntry {
            /** @var ValueEntry $lockedExpected */
            $lockedExpected = ValueEntry::query()
                ->lockForUpdate()
                ->findOrFail($expectedEntry->id);

            /** @var ValueEntry $lockedActual */
            $lockedActual = ValueEntry::query()
                ->lockForUpdate()
                ->findOrFail($actualEntry->id);

            $idempotencyKey = hash('sha256', implode('|', [
                'manufacturing-expected-cost-clearing',
                $lockedExpected->id,
                $lockedActual->id,
                DecimalMath::quantity($quantityBase),
                DecimalMath::amount($amountToClear),
            ]));

            $existing = ValueEntry::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $clearingEntry = ValueEntry::query()->create([
                'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
                'item_ledger_entry_no' => $lockedExpected->item_ledger_entry_no,
                'item_ledger_entry_type' => $lockedExpected->item_ledger_entry_type,
                'item_no' => $lockedExpected->item_no,
                'location_code' => $lockedExpected->location_code,
                'posting_date' => $lockedActual->posting_date,
                'valuation_date' => $lockedActual->valuation_date ?? $lockedActual->posting_date,
                'document_type' => 'PROD_EXP_COST_CLEAR',
                'document_no' => $lockedActual->document_no ?? $lockedExpected->document_no,
                'document_line_no' => $lockedActual->document_line_no,
                'description' => 'Clear expected manufacturing cost',
                'quantity' => DecimalMath::quantity(-abs($quantityBase)),
                'invoiced_quantity' => DecimalMath::quantity($quantityBase),
                'valued_quantity' => DecimalMath::quantity(-abs($quantityBase)),
                'remaining_quantity' => 0,
                'costing_method' => $lockedExpected->costing_method,
                'cost_component' => 'expected_cost_clearing',
                'value_entry_state' => 'clearing',
                'cost_amount_actual' => 0,
                'cost_amount_expected' => DecimalMath::amount(-abs($amountToClear)),
                'cost_amount_actual_acy' => 0,
                'cost_amount_expected_acy' => DecimalMath::amount(-abs($amountToClear)),
                'unit_cost' => DecimalMath::unitCost($quantityBase > 0 ? $amountToClear / $quantityBase : 0),
                'unit_cost_acy' => DecimalMath::unitCost($quantityBase > 0 ? $amountToClear / $quantityBase : 0),
                'source_type' => 'PROD_EXP_COST_CLEAR',
                'source_module' => 'manufacturing',
                'source_id' => $lockedActual->id,
                'source_number' => $lockedActual->document_no,
                'source_no' => $lockedExpected->source_no,
                'source_line_no' => $lockedActual->source_line_no,
                'production_order_no' => $lockedExpected->production_order_no,
                'production_order_line_no' => $lockedExpected->production_order_line_no,
                'production_order_component_line_no' => $lockedExpected->production_order_component_line_no,
                'prod_order_line_item_no' => $lockedExpected->prod_order_line_item_no,
                'expected_cost' => true,
                'reversal_of_value_entry_id' => $lockedExpected->id,
                'original_entry_no' => $lockedExpected->id,
                'idempotency_key' => $idempotencyKey,
                'accounting_metadata' => [
                    'phase_1d_expected_manufacturing_cost_clearing' => true,
                    'expected_value_entry_id' => $lockedExpected->id,
                    'actual_value_entry_id' => $lockedActual->id,
                    'cleared_quantity_base' => $quantityBase,
                    'cleared_expected_amount' => $amountToClear,
                ],
                'user_id' => $userId ? (string) $userId : (auth()->id() ? (string) auth()->id() : null),
            ]);

            if (config('accounts.post_expected_inventory_cost_to_gl', false) && $lockedExpected->gl_posted) {
                $this->accountingOrchestrator->post($clearingEntry);
            }

            return $clearingEntry->fresh();
        });
    }

    private function idempotencyKey(ValueEntry $expectedEntry, PurchaseInvoice $invoice, PurchaseInvoiceLine $line, float $quantityBase): string
    {
        return hash('sha256', implode('|', [
            'expected-cost-clearing',
            $expectedEntry->id,
            $invoice->id,
            $line->id,
            DecimalMath::quantity($quantityBase),
        ]));
    }
}

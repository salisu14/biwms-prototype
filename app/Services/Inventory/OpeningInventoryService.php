<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\ItemLedgerEntryType;
use App\Enums\SourceType;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\OpeningInventory;
use App\Models\OpeningInventoryLine;
use App\Services\AuditTrailService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\DB;

class OpeningInventoryService
{
    public function __construct(
        private readonly InventoryBalanceService $inventoryBalanceService,
        private readonly AuditTrailService $auditTrailService,
        private readonly ValueEntryService $valueEntryService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createDraft(
        string $documentNumber,
        string $source,
        mixed $postingDate,
        array $lines,
        ?int $businessId = null,
        ?int $createdBy = null,
        ?string $description = null,
    ): OpeningInventory {
        return DB::transaction(function () use ($businessId, $createdBy, $description, $documentNumber, $lines, $postingDate, $source): OpeningInventory {
            $openingInventory = OpeningInventory::query()->firstOrCreate([
                'document_number' => $documentNumber,
            ], [
                'business_id' => $businessId,
                'posting_date' => $postingDate,
                'status' => OpeningInventory::STATUS_DRAFT,
                'source' => $source,
                'description' => $description,
                'created_by' => $createdBy,
            ]);

            if ($openingInventory->status === OpeningInventory::STATUS_POSTED) {
                return $openingInventory;
            }

            foreach (array_values($lines) as $index => $line) {
                $item = Item::query()->findOrFail((int) $line['item_id']);
                $quantity = DecimalMath::quantity($line['quantity']);
                $unitCost = DecimalMath::unitCost($line['unit_cost'] ?? $item->unit_cost ?? 0);
                $quantityBase = $this->baseQuantity($item, $quantity, $line['unit_of_measure_code'] ?? null);
                $amount = DecimalMath::amount(DecimalMath::mul($quantityBase, $unitCost, DecimalPrecision::AMOUNT_SCALE));

                OpeningInventoryLine::query()->updateOrCreate([
                    'opening_inventory_id' => $openingInventory->id,
                    'line_number' => ($index + 1) * 10000,
                ], [
                    'item_id' => $item->id,
                    'location_id' => (int) $line['location_id'],
                    'unit_of_measure_id' => $line['unit_of_measure_id'] ?? $item->base_uom_id,
                    'quantity' => $quantity,
                    'quantity_base' => $quantityBase,
                    'unit_cost' => $unitCost,
                    'amount' => $amount,
                    'lot_number' => $line['lot_number'] ?? null,
                    'serial_number' => $line['serial_number'] ?? null,
                ]);
            }

            return $openingInventory->fresh('lines.item');
        });
    }

    public function post(OpeningInventory $openingInventory, ?int $userId = null): OpeningInventory
    {
        return DB::transaction(function () use ($openingInventory, $userId): OpeningInventory {
            /** @var OpeningInventory $document */
            $document = OpeningInventory::query()
                ->with('lines.item')
                ->whereKey($openingInventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->status === OpeningInventory::STATUS_POSTED) {
                return $document;
            }

            if ($document->status !== OpeningInventory::STATUS_DRAFT) {
                throw new \RuntimeException("Opening inventory {$document->document_number} cannot be posted from status {$document->status}.");
            }

            if ($document->lines->isEmpty()) {
                throw new \RuntimeException("Opening inventory {$document->document_number} has no lines.");
            }

            foreach ($document->lines as $line) {
                $this->postLine($document, $line);
                $this->inventoryBalanceService->recalculateItem((int) $line->item_id);
            }

            $this->postOpeningGlEntries($document);

            $document->forceFill([
                'status' => OpeningInventory::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $userId,
            ])->save();

            $this->auditTrailService->recordPosting(
                auditable: $document,
                userId: $userId,
                documentType: 'OPENING_INVENTORY',
                documentNo: $document->document_number,
                metadata: [
                    'source' => $document->source,
                    'line_count' => $document->lines->count(),
                ],
                description: "Posted opening inventory {$document->document_number}",
            );

            return $document->fresh('lines.itemLedgerEntry');
        });
    }

    private function postLine(OpeningInventory $document, OpeningInventoryLine $line): void
    {
        if (! DecimalMath::isPositive($line->quantity_base)) {
            throw new \RuntimeException("Opening inventory line {$line->line_number} quantity must be positive.");
        }

        if (! DecimalMath::isPositive($line->unit_cost)) {
            throw new \RuntimeException("Opening inventory line {$line->line_number} requires a positive unit cost.");
        }

        $existingEntry = ItemLedgerEntry::query()
            ->where('source_type', OpeningInventory::class)
            ->where('source_id', $document->id)
            ->where('document_line_number', $line->line_number)
            ->first();

        if ($existingEntry) {
            $line->forceFill(['item_ledger_entry_id' => $existingEntry->id])->save();

            return;
        }

        $itemLedgerEntry = ItemLedgerEntry::query()->create([
            'entry_number' => $this->nextItemLedgerEntryNumber(),
            'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
            'document_type' => 'OPENING_INVENTORY',
            'document_number' => $document->document_number,
            'document_line_number' => $line->line_number,
            'item_id' => $line->item_id,
            'location_id' => $line->location_id,
            'quantity' => $line->quantity_base,
            'remaining_quantity' => $line->quantity_base,
            'open' => true,
            'posting_date' => $document->posting_date,
            'entry_date' => now(),
            'source_type' => OpeningInventory::class,
            'source_id' => $document->id,
            'cost_amount_actual' => $line->amount,
            'cost_amount_expected' => '0.0000',
            'purchase_amount_actual' => '0.0000',
            'general_product_posting_group_id' => $line->item->general_product_posting_group_id,
            'inventory_posting_group_id' => $line->item->inventory_posting_group_id,
            'lot_number' => $line->lot_number,
            'serial_number' => $line->serial_number,
        ]);

        $valueEntry = $this->valueEntryService->ensureForItemLedgerEntry($itemLedgerEntry);

        if (! $valueEntry) {
            throw new \RuntimeException("Opening inventory line {$line->line_number} failed to create a value entry.");
        }

        $line->forceFill(['item_ledger_entry_id' => $itemLedgerEntry->id])->save();
    }

    private function nextItemLedgerEntryNumber(): int
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("select pg_advisory_xact_lock(hashtext('item_ledger_entries_entry_number'))");
        }

        return (int) ItemLedgerEntry::query()->max('entry_number') + 1;
    }

    private function postOpeningGlEntries(OpeningInventory $document): void
    {
        if (GlEntry::query()
            ->where('document_type', 'OPENING_INVENTORY')
            ->where('document_number', $document->document_number)
            ->exists()) {
            return;
        }

        $openingEquityAccount = ChartOfAccount::query()
            ->where('account_number', '30100')
            ->where('direct_posting', true)
            ->first();

        if (! $openingEquityAccount) {
            return;
        }

        $transactionNumber = $this->nextGlTransactionNumber();

        foreach ($document->lines as $line) {
            $inventoryAccount = InventoryPostingSetup::getFor(
                (int) $line->item->inventory_posting_group_id,
                (int) $line->location_id
            )?->inventoryAccount;

            if (! $inventoryAccount) {
                continue;
            }

            $amount = DecimalMath::currency($line->amount);
            if (! DecimalMath::isPositive($amount)) {
                continue;
            }

            $this->createGlEntry($document, $line, $transactionNumber, (int) $inventoryAccount->id, debit: $amount, credit: '0.00');
            $this->createGlEntry($document, $line, $transactionNumber, (int) $openingEquityAccount->id, debit: '0.00', credit: $amount);
        }
    }

    private function createGlEntry(
        OpeningInventory $document,
        OpeningInventoryLine $line,
        int $transactionNumber,
        int $chartOfAccountId,
        string $debit,
        string $credit,
    ): void {
        GlEntry::query()->create([
            'entry_number' => $this->nextGlEntryNumber(),
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $chartOfAccountId,
            'debit_amount' => $debit,
            'debit_amount_lcy' => $debit,
            'credit_amount' => $credit,
            'credit_amount_lcy' => $credit,
            'amount' => DecimalMath::currency(DecimalMath::sub($debit, $credit, 2)),
            'amount_lcy' => DecimalMath::currency(DecimalMath::sub($debit, $credit, 2)),
            'source_type' => SourceType::ITEM,
            'source_number' => $line->item?->item_code,
            'document_type' => 'OPENING_INVENTORY',
            'document_number' => $document->document_number,
            'document_date' => $document->posting_date,
            'posting_date' => $document->posting_date,
            'description' => "Opening inventory {$line->item?->item_code}",
            'item_ledger_entry_id' => $line->item_ledger_entry_id,
            'sourceable_type' => OpeningInventory::class,
            'sourceable_id' => $document->id,
        ]);
    }

    private function nextGlEntryNumber(): int
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("select pg_advisory_xact_lock(hashtext('gl_entries_entry_number'))");
        }

        return (int) GlEntry::query()->max('entry_number') + 1;
    }

    private function nextGlTransactionNumber(): int
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("select pg_advisory_xact_lock(hashtext('gl_entries_transaction_number'))");
        }

        return (int) GlEntry::query()->max('transaction_number') + 1;
    }

    private function baseQuantity(Item $item, string $quantity, ?string $uomCode): string
    {
        if ($uomCode === null || $uomCode === '' || $uomCode === $item->baseUom?->uom_code) {
            return $quantity;
        }

        return DecimalMath::mul($quantity, $item->getConversionFactorForUomDecimal($uomCode), DecimalPrecision::QUANTITY_SCALE);
    }
}

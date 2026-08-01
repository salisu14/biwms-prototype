<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\ItemLedgerEntryType;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\OpeningInventory;
use App\Models\OpeningInventoryLine;
use App\Models\UnitOfMeasure;
use App\Services\AuditTrailService;
use App\Services\PostingDateValidator;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OpeningInventoryService
{
    public function __construct(
        private readonly InventoryBalanceService $inventoryBalanceService,
        private readonly AuditTrailService $auditTrailService,
        private readonly ValueEntryService $valueEntryService,
        private readonly ValueEntryAccountingOrchestrator $valueEntryAccountingOrchestrator,
        private readonly PostingDateValidator $postingDateValidator,
        private readonly CostingPeriodService $costingPeriodService,
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
            $this->assertBusinessSelected($businessId);
            $this->assertDocumentNumberAvailable($documentNumber, $businessId);
            $this->postingDateValidator->validate($postingDate);
            $this->costingPeriodService->assertApplicationMutable($postingDate);

            $openingInventory = OpeningInventory::query()->create([
                'business_id' => $businessId,
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'status' => OpeningInventory::STATUS_DRAFT,
                'source' => $source,
                'description' => $description,
                'created_by' => $createdBy,
            ]);

            $this->syncDraftLines($openingInventory, $lines);

            return $openingInventory->fresh('lines.item');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function updateDraft(OpeningInventory $openingInventory, array $data, array $lines): OpeningInventory
    {
        return DB::transaction(function () use ($data, $lines, $openingInventory): OpeningInventory {
            /** @var OpeningInventory $document */
            $document = OpeningInventory::query()
                ->with('lines')
                ->whereKey($openingInventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraft($document, 'updated');

            $postingDate = $data['posting_date'] ?? $document->posting_date;
            $this->postingDateValidator->validate($postingDate);
            $this->costingPeriodService->assertApplicationMutable($postingDate);

            $businessId = array_key_exists('business_id', $data) ? (int) $data['business_id'] : $document->business_id;
            $this->assertBusinessSelected($businessId);
            $documentNumber = (string) ($data['document_number'] ?? $document->document_number);

            if ($documentNumber !== $document->document_number || (int) $businessId !== (int) $document->business_id) {
                $this->assertDocumentNumberAvailable($documentNumber, $businessId, $document->id);
            }

            $document->fill([
                'business_id' => $businessId ?: null,
                'document_number' => $documentNumber,
                'posting_date' => $postingDate,
                'source' => $data['source'] ?? $document->source,
                'description' => $data['description'] ?? $document->description,
            ]);
            $document->save();

            $this->syncDraftLines($document, $lines);

            return $document->fresh('lines.item');
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

            $this->assertDraft($document, 'posted');
            $this->postingDateValidator->validate($document->posting_date);
            $this->costingPeriodService->assertApplicationMutable($document->posting_date);

            if ($document->lines->isEmpty()) {
                throw new RuntimeException("Opening inventory {$document->document_number} has no lines.");
            }

            foreach ($document->lines as $line) {
                $this->postLine($document, $line, $userId);
                $this->inventoryBalanceService->recalculateItem((int) $line->item_id);
            }

            OpeningInventory::allowServiceStatusTransition(fn (): bool => $document->forceFill([
                'status' => OpeningInventory::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $userId,
            ])->save());

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

    public function cancelDraft(OpeningInventory $openingInventory, ?int $userId = null): OpeningInventory
    {
        return DB::transaction(function () use ($openingInventory, $userId): OpeningInventory {
            /** @var OpeningInventory $document */
            $document = OpeningInventory::query()
                ->whereKey($openingInventory->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertDraft($document, 'cancelled');

            if ($document->lines()->whereNotNull('item_ledger_entry_id')->exists()) {
                throw new RuntimeException("Opening inventory {$document->document_number} has ledger records and cannot be cancelled.");
            }

            OpeningInventory::allowServiceStatusTransition(fn (): bool => $document->forceFill(['status' => OpeningInventory::STATUS_CANCELLED])->save());

            $this->auditTrailService->recordGeneric(
                eventType: 'inventory',
                action: 'opening_inventory_cancelled',
                auditable: $document,
                documentType: 'OPENING_INVENTORY',
                documentNo: $document->document_number,
                userId: $userId,
                description: "Cancelled opening inventory {$document->document_number}",
                metadata: [
                    'source' => $document->source,
                    'business_id' => $document->business_id,
                ],
            );

            return $document->fresh('lines');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncDraftLines(OpeningInventory $document, array $lines): void
    {
        $this->assertDraft($document, 'updated');

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Opening inventory must contain at least one line.']);
        }

        $retainedLineIds = [];
        $serialNumbers = [];

        foreach (array_values($lines) as $index => $line) {
            $normalized = $this->normalizeLine($document, $line, ($index + 1) * 10000);

            if ($normalized['serial_number'] !== null && $normalized['serial_number'] !== '') {
                $serialKey = $normalized['item_id'].':'.$normalized['serial_number'];
                if (isset($serialNumbers[$serialKey])) {
                    throw ValidationException::withMessages(['lines' => "Duplicate serial number {$normalized['serial_number']} on opening inventory lines."]);
                }

                $serialNumbers[$serialKey] = true;
            }

            $lineId = isset($line['id']) ? (int) $line['id'] : null;
            $openingInventoryLine = $lineId
                ? OpeningInventoryLine::query()
                    ->where('opening_inventory_id', $document->id)
                    ->whereKey($lineId)
                    ->first()
                : null;

            if ($openingInventoryLine) {
                if ($openingInventoryLine->item_ledger_entry_id !== null) {
                    throw new RuntimeException("Opening inventory line {$openingInventoryLine->line_number} has ledger records and cannot be updated.");
                }

                $openingInventoryLine->fill($normalized);
                $openingInventoryLine->save();
            } else {
                $openingInventoryLine = OpeningInventoryLine::query()->create([
                    'opening_inventory_id' => $document->id,
                    ...$normalized,
                ]);
            }

            $retainedLineIds[] = $openingInventoryLine->id;
        }

        OpeningInventoryLine::query()
            ->where('opening_inventory_id', $document->id)
            ->whereNotIn('id', $retainedLineIds)
            ->whereNull('item_ledger_entry_id')
            ->delete();

        if (OpeningInventoryLine::query()
            ->where('opening_inventory_id', $document->id)
            ->whereNotIn('id', $retainedLineIds)
            ->exists()) {
            throw new RuntimeException("Opening inventory {$document->document_number} contains posted lines that cannot be removed.");
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function normalizeLine(OpeningInventory $document, array $line, int $lineNumber): array
    {
        $item = Item::query()->with(['baseUom', 'uomAssignments.uom'])->findOrFail((int) $line['item_id']);
        $location = Location::query()->findOrFail((int) $line['location_id']);

        $this->assertSameBusiness($document->business_id, $item, 'item');
        $this->assertSameBusiness($document->business_id, $location, 'location');

        $quantity = DecimalMath::quantity($line['quantity'] ?? 0);
        $unitCost = DecimalMath::unitCost($line['unit_cost'] ?? $item->unit_cost ?? 0);

        if (! DecimalMath::isPositive($quantity)) {
            throw ValidationException::withMessages(['lines' => "Opening inventory line {$lineNumber} quantity must be positive."]);
        }

        if (! DecimalMath::isPositive($unitCost)) {
            throw ValidationException::withMessages(['lines' => "Opening inventory line {$lineNumber} requires a positive unit cost."]);
        }

        $unitOfMeasureId = isset($line['unit_of_measure_id']) && filled($line['unit_of_measure_id'])
            ? (int) $line['unit_of_measure_id']
            : (int) $item->base_uom_id;
        $unitOfMeasure = UnitOfMeasure::query()->find($unitOfMeasureId);
        $quantityBase = $this->baseQuantity($item, $quantity, $unitOfMeasure);
        $amount = DecimalMath::amount(DecimalMath::mul($quantityBase, $unitCost, DecimalPrecision::AMOUNT_SCALE));
        $lotNumber = filled($line['lot_number'] ?? null) ? (string) $line['lot_number'] : null;
        $serialNumber = filled($line['serial_number'] ?? null) ? (string) $line['serial_number'] : null;

        $this->validateItemTracking($item, $lotNumber, $serialNumber);

        return [
            'item_id' => $item->id,
            'location_id' => $location->id,
            'unit_of_measure_id' => $unitOfMeasureId ?: null,
            'quantity' => $quantity,
            'quantity_base' => $quantityBase,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'line_number' => $lineNumber,
            'lot_number' => $lotNumber,
            'serial_number' => $serialNumber,
        ];
    }

    private function postLine(OpeningInventory $document, OpeningInventoryLine $line, ?int $userId): void
    {
        if (! DecimalMath::isPositive($line->quantity_base)) {
            throw new RuntimeException("Opening inventory line {$line->line_number} quantity must be positive.");
        }

        if (! DecimalMath::isPositive($line->unit_cost)) {
            throw new RuntimeException("Opening inventory line {$line->line_number} requires a positive unit cost.");
        }

        $existingEntry = ItemLedgerEntry::query()
            ->where('source_type', OpeningInventory::class)
            ->where('source_id', $document->id)
            ->where('document_line_number', $line->line_number)
            ->first();

        if ($existingEntry) {
            $line->forceFill(['item_ledger_entry_id' => $existingEntry->id])->save();

            $this->postValueEntryAccounting($existingEntry, $userId);

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
            'general_business_posting_group_id' => $this->generalBusinessPostingGroupIdFor($line),
            'general_product_posting_group_id' => $line->item->general_product_posting_group_id,
            'inventory_posting_group_id' => $line->item->inventory_posting_group_id,
            'lot_number' => $line->lot_number,
            'serial_number' => $line->serial_number,
        ]);

        $valueEntry = $this->valueEntryService->ensureForItemLedgerEntry($itemLedgerEntry);

        if (! $valueEntry) {
            throw new RuntimeException("Opening inventory line {$line->line_number} failed to create a value entry.");
        }

        $line->forceFill(['item_ledger_entry_id' => $itemLedgerEntry->id])->save();
        $this->postValueEntryAccounting($itemLedgerEntry, $userId);
    }

    private function nextItemLedgerEntryNumber(): int
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("select pg_advisory_xact_lock(hashtext('item_ledger_entries_entry_number'))");
        }

        return (int) ItemLedgerEntry::query()->max('entry_number') + 1;
    }

    private function generalBusinessPostingGroupIdFor(OpeningInventoryLine $line): ?int
    {
        $item = $line->item;
        $itemBusinessPostingGroupId = $item->getAttribute('general_business_posting_group_id');

        if ($itemBusinessPostingGroupId !== null) {
            return (int) $itemBusinessPostingGroupId;
        }

        $openingBusinessPostingGroupId = GeneralBusinessPostingGroup::query()
            ->where('code', 'OPENING')
            ->value('id');

        $setup = $openingBusinessPostingGroupId
            ? GeneralPostingSetup::query()
                ->where('general_business_posting_group_id', $openingBusinessPostingGroupId)
                ->where('general_product_posting_group_id', $item->general_product_posting_group_id)
                ->where('blocked', false)
                ->whereNotNull('inventory_adj_account_id')
                ->first()
            : null;

        $setup ??= GeneralPostingSetup::query()
            ->where('general_product_posting_group_id', $item->general_product_posting_group_id)
            ->where('blocked', false)
            ->whereNotNull('inventory_adj_account_id')
            ->orderBy('id')
            ->first()
            ?? GeneralPostingSetup::query()
                ->where('general_product_posting_group_id', $item->general_product_posting_group_id)
                ->where('blocked', false)
                ->orderBy('id')
                ->first();

        return $setup?->general_business_posting_group_id;
    }

    private function postValueEntryAccounting(ItemLedgerEntry $itemLedgerEntry, ?int $userId): void
    {
        $valueEntry = $this->valueEntryService->ensureForItemLedgerEntry($itemLedgerEntry);

        if (! $valueEntry) {
            throw new RuntimeException("Item ledger entry {$itemLedgerEntry->entry_number} failed to create a value entry.");
        }

        if ($userId !== null && blank($valueEntry->user_id)) {
            $valueEntry->forceFill(['user_id' => (string) $userId])->save();
        }

        $this->valueEntryAccountingOrchestrator->post($valueEntry);
    }

    private function baseQuantity(Item $item, string $quantity, ?UnitOfMeasure $unitOfMeasure): string
    {
        if (! $unitOfMeasure || $unitOfMeasure->id === $item->base_uom_id || $unitOfMeasure->uom_code === $item->baseUom?->uom_code) {
            return $quantity;
        }

        return DecimalMath::mul($quantity, $item->getConversionFactorForUomDecimal($unitOfMeasure->uom_code), DecimalPrecision::QUANTITY_SCALE);
    }

    private function assertDraft(OpeningInventory $document, string $action): void
    {
        if ($document->status !== OpeningInventory::STATUS_DRAFT) {
            throw new RuntimeException("Opening inventory {$document->document_number} cannot be {$action} from status {$document->status}.");
        }
    }

    private function assertDocumentNumberAvailable(string $documentNumber, ?int $businessId, ?int $ignoreId = null): void
    {
        $exists = OpeningInventory::query()
            ->where('document_number', $documentNumber)
            ->where('business_id', $businessId)
            ->when($ignoreId, fn ($query): mixed => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'document_number' => "Opening inventory document number {$documentNumber} already exists for this business.",
            ]);
        }
    }

    private function assertBusinessSelected(?int $businessId): void
    {
        if ($businessId === null || $businessId <= 0) {
            throw ValidationException::withMessages([
                'business_id' => 'Opening inventory requires a business.',
            ]);
        }
    }

    private function assertSameBusiness(?int $businessId, mixed $model, string $label): void
    {
        if ($businessId === null || ! isset($model->business_id) || $model->business_id === null) {
            return;
        }

        if ((int) $model->business_id !== $businessId) {
            throw ValidationException::withMessages([
                'business_id' => "Selected {$label} belongs to another business.",
            ]);
        }
    }

    private function validateItemTracking(Item $item, ?string $lotNumber, ?string $serialNumber): void
    {
        $trackingCode = strtoupper((string) $item->item_tracking_code);

        if ($trackingCode === '') {
            return;
        }

        if (str_contains($trackingCode, 'LOT') && blank($lotNumber)) {
            throw ValidationException::withMessages(['lot_number' => "Lot number is required for item {$item->item_code}."]);
        }

        if ((str_contains($trackingCode, 'SERIAL') || str_contains($trackingCode, 'SN')) && blank($serialNumber)) {
            throw ValidationException::withMessages(['serial_number' => "Serial number is required for item {$item->item_code}."]);
        }
    }
}

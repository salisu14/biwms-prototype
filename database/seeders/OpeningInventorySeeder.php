<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use Illuminate\Database\Seeder;
use RuntimeException;

class OpeningInventorySeeder extends Seeder
{
    public const DOCUMENT_NUMBER = 'SEED-OPENING-V1';

    /**
     * @var array<string, string>
     */
    private const OPENING_QUANTITIES = [
        '1000' => '50.00000000',
        '2100' => '25000.00000000',
        '2200' => '400000.00000000',
        '2300' => '1000000.00000000',
        '2400' => '150000.00000000',
        '2410' => '150000.00000000',
        '2500' => '10000.00000000',
        '2600' => '10000.00000000',
        '2700' => '1000.00000000',
        '2800' => '50000.00000000',
        '2900' => '10000.00000000',
        '3000' => '200000.00000000',
        '3100' => '1000000.00000000',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Opening inventory seeding is disabled in production.');
        }

        $existingDocument = OpeningInventory::query()
            ->where('document_number', self::DOCUMENT_NUMBER)
            ->first();

        if ($existingDocument?->status === OpeningInventory::STATUS_POSTED) {
            $this->command?->info('Seed opening inventory already posted; skipping.');

            return;
        }

        if ($this->hasOperationalLedgerEntries()) {
            throw new RuntimeException(
                'Opening inventory seeding refused because operational item-ledger entries already exist.'
            );
        }

        $defaultLocation = Location::query()->orderBy('id')->firstOrFail();
        $items = Item::query()
            ->with('baseUom')
            ->whereIn('item_code', array_keys(self::OPENING_QUANTITIES))
            ->get()
            ->keyBy('item_code');

        $lines = [];
        foreach (self::OPENING_QUANTITIES as $itemCode => $quantity) {
            /** @var Item|null $item */
            $item = $items->get($itemCode);
            if (! $item) {
                continue;
            }

            $lines[] = [
                'item_id' => $item->id,
                'location_id' => $item->location_id ?: $defaultLocation->id,
                'unit_of_measure_id' => $item->base_uom_id,
                'unit_of_measure_code' => $item->baseUom?->uom_code,
                'quantity' => $quantity,
                'unit_cost' => $item->unit_cost,
            ];
        }

        $document = app(OpeningInventoryService::class)->createDraft(
            documentNumber: self::DOCUMENT_NUMBER,
            source: 'SEED_OPENING_STOCK',
            postingDate: now()->toDateString(),
            lines: $lines,
            description: 'Seeded demo opening inventory. Subledger only; no G/L opening-balance posting.',
        );

        app(OpeningInventoryService::class)->post($document);

        $this->command?->info('Seed opening inventory posted successfully.');
    }

    private function hasOperationalLedgerEntries(): bool
    {
        return ItemLedgerEntry::query()
            ->where(function ($query): void {
                $query->where('source_type', '!=', OpeningInventory::class)
                    ->orWhereNull('source_type');
            })
            ->exists();
    }
}

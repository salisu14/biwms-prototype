<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Models\AccountingPeriod;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class OpeningInventorySeeder extends Seeder
{
    public const DOCUMENT_NUMBER = 'SEED-OPENING-V1';

    /**
     * Demo opening quantities.
     *
     * This data is used only in non-production environments.
     * Real production opening stock must be entered and posted through
     * the Opening Inventory Filament workflow after physical stock count.
     *
     * @var array<string, string>
     */
    private const array OPENING_QUANTITIES = [
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

    /**
     * @throws Throwable
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Opening inventory seeding is disabled in production. '
                .'This seeder contains demo quantities; use the Opening Inventory '
                .'Filament workflow for real production stock.',
            );
        }

        DB::transaction(function (): void {
            $business = $this->business();

            $existingDocument = OpeningInventory::query()
                ->where('business_id', $business->id)
                ->where('document_number', self::DOCUMENT_NUMBER)
                ->first();

            if (
                $existingDocument?->status
                === OpeningInventory::STATUS_POSTED
            ) {
                $this->command?->info(
                    'Demo opening inventory is already posted; skipping.',
                );

                return;
            }

            /*
             * A non-posted document from an interrupted prior seeder run is
             * removed and recreated. This is safe here because this seeder is
             * non-production only and the document has not posted any ledger
             * records.
             */
            if ($existingDocument !== null) {
                $hasLinkedLedgerEntries = $existingDocument->lines()
                    ->whereNotNull('item_ledger_entry_id')
                    ->exists();

                if ($hasLinkedLedgerEntries) {
                    throw new RuntimeException(
                        'The existing demo opening inventory document has '
                        .'linked ledger entries but is not marked as posted. '
                        .'Run the opening-inventory repair/reconciliation '
                        .'process before reseeding.',
                    );
                }

                $existingDocument->lines()->delete();
                $existingDocument->delete();
            }

            if ($this->hasOperationalLedgerEntries()) {

                if ($this->hasOperationalLedgerEntries()) {
                    throw new RuntimeException(
                        'Opening inventory seeding was refused because '
                        .'operational Item Ledger Entries already exist.',
                    );
                }
            }

            $postingDate = $this->postingDate();

            $this->ensureAccountingPeriod($postingDate);

            $items = Item::query()
                ->with('baseUom')
                ->whereIn(
                    'item_code',
                    array_keys(self::OPENING_QUANTITIES),
                )
                ->get()
                ->keyBy('item_code');

            $this->validateAllItemsExist($items);
            $this->ensureOpeningGeneralPostingSetups($items);
            $this->validateInventoryPostingSetups($items);

            $lines = $this->buildOpeningInventoryLines($items);

            if ($lines === []) {
                throw new RuntimeException(
                    'No valid Opening Inventory lines were generated.',
                );
            }

            $service = app(OpeningInventoryService::class);

            $document = $service->createDraft(
                documentNumber: self::DOCUMENT_NUMBER,
                source: 'DEMO_SEED_OPENING_STOCK',
                postingDate: $postingDate->toDateString(),
                lines: $lines,
                businessId: $business->id,
                description: 'Demo opening inventory posted through the '
                .'normal Item Ledger, Value Entry and G/L workflow.',
            );

            $postedDocument = $service->post($document);

            if (
                $postedDocument->status
                !== OpeningInventory::STATUS_POSTED
            ) {
                throw new RuntimeException(
                    'Opening Inventory service returned without marking '
                    .'the document as posted.',
                );
            }

            $this->command?->info(
                'Demo opening inventory posted successfully.',
            );

            $this->command?->info(
                'Document: '.$postedDocument->document_number,
            );

            $this->command?->info(
                'Lines: '.count($lines),
            );
        });
    }

    /**
     * Determine the demo posting date.
     *
     * This may be overridden in config/services.php or another suitable
     * configuration file:
     *
     * 'demo_opening_inventory_date' => env(
     *     'DEMO_OPENING_INVENTORY_DATE',
     *     now()->toDateString(),
     * ),
     */
    private function postingDate(): Carbon
    {
        $configuredDate = config(
            'biwms.demo_opening_inventory_date',
        );

        if (is_string($configuredDate) && $configuredDate !== '') {
            return Carbon::parse($configuredDate)->startOfDay();
        }

        return Carbon::today();
    }

    /**
     * Refuse demo opening stock when normal operational inventory activity
     * already exists for the selected Business.
     */
    private function hasOperationalLedgerEntries(): bool
    {
        return ItemLedgerEntry::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('source_type')
                    ->orWhere(
                        'source_type',
                        '!=',
                        OpeningInventory::class,
                    );
            })
            ->exists();
    }

    private function ensureAccountingPeriod(
        Carbon $postingDate,
    ): void {
        if (
            AccountingPeriod::query()
                ->containingDate($postingDate)
                ->exists()
        ) {
            return;
        }

        AccountingPeriod::query()->create([
            'name' => 'FY'.$postingDate->year,
            'start_date' => $postingDate
                ->copy()
                ->startOfYear()
                ->toDateString(),
            'end_date' => $postingDate
                ->copy()
                ->endOfYear()
                ->toDateString(),
            'is_closed' => false,
        ]);
    }

    private function business(): Business
    {
        return Business::query()->firstOrCreate(
            ['code' => 'BIWMS'],
            [
                'name' => 'BIWMS',
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  Collection<string, Item>  $items
     */
    private function validateAllItemsExist(
        Collection $items,
    ): void {
        $missingItemCodes = collect(
            array_keys(self::OPENING_QUANTITIES),
        )
            ->reject(
                fn (string $itemCode): bool => $items->has($itemCode),
            )
            ->values();

        if ($missingItemCodes->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'The following Opening Inventory Items were not found: '
            .$missingItemCodes->implode(', ')
            .'. Run ItemSeeder before OpeningInventorySeeder.',
        );
    }

    /**
     * Ensure the OPENING business posting group and Opening Balance Equity
     * mapping exist for every Product Posting Group used by the Items.
     *
     * @param  Collection<string, Item>  $items
     */
    private function ensureOpeningGeneralPostingSetups(
        Collection $items,
    ): void {
        $businessPostingGroup =
            GeneralBusinessPostingGroup::query()->firstOrCreate(
                ['code' => 'OPENING'],
                [
                    'description' => 'Opening Inventory',
                    'default_vat_business_posting_group_id' => null,
                    'auto_create_vat_bus_posting_group' => false,
                    'blocked' => false,
                ],
            );

        $openingEquityAccount =
            ChartOfAccount::query()->firstOrCreate(
                ['account_number' => '30100'],
                [
                    'name' => 'Opening Balance Equity',
                    'structural_type' => AccountStructuralType::POSTING,
                    'account_category' => AccountCategory::EQUITY,
                    'income_balance' => IncomeBalanceType::BALANCE_SHEET,
                    'direct_posting' => true,
                    'blocked' => false,
                ],
            );

        $productPostingGroupIds = $items
            ->pluck('general_product_posting_group_id')
            ->filter()
            ->unique()
            ->values();

        if ($productPostingGroupIds->isEmpty()) {
            throw new RuntimeException(
                'None of the Opening Inventory Items has a General '
                .'Product Posting Group.',
            );
        }

        foreach ($productPostingGroupIds as $productPostingGroupId) {
            GeneralPostingSetup::query()->updateOrCreate(
                [
                    'general_business_posting_group_id' => $businessPostingGroup->id,
                    'general_product_posting_group_id' => $productPostingGroupId,
                ],
                [
                    /*
                     * The Opening Inventory accounting mapping is:
                     *
                     * Dr Inventory
                     * Cr Opening Balance Equity
                     *
                     * The Inventory account is resolved from Inventory
                     * Posting Setup. The offset is resolved here.
                     */
                    'inventory_adj_account_id' => $openingEquityAccount->id,
                    'blocked' => false,
                ],
            );
        }
    }

    /**
     * Validate that every Item has the location-specific Inventory Posting
     * Setup required to resolve its Inventory G/L account.
     *
     * @param  Collection<string, Item>  $items
     */
    private function validateInventoryPostingSetups(
        Collection $items,
    ): void {
        $errors = [];

        foreach ($items as $item) {
            if (! $item->inventory_posting_group_id) {
                $errors[] = "Item {$item->item_code} has no "
                    .'Inventory Posting Group.';

                continue;
            }

            if (! $item->location_id) {
                $errors[] = "Item {$item->item_code} has no "
                    .'default Location.';

                continue;
            }

            $postingSetup = InventoryPostingSetup::query()
                ->where(
                    'inventory_posting_group_id',
                    $item->inventory_posting_group_id,
                )
                ->where('location_id', $item->location_id)
                ->first();

            if ($postingSetup === null) {
                $errors[] = "Item {$item->item_code} is missing an "
                    .'Inventory Posting Setup for inventory group '
                    ."{$item->inventory_posting_group_id} and "
                    ."location {$item->location_id}.";

                continue;
            }

            /*
             * Keep the Item's setup reference synchronized, but do not touch
             * stock or create any inventory entries.
             */
            if (
                (int) $item->inventory_posting_setup_id
                !== (int) $postingSetup->id
            ) {
                $item->forceFill([
                    'inventory_posting_setup_id' => $postingSetup->id,
                ])->save();
            }
        }

        if ($errors === []) {
            return;
        }

        throw new RuntimeException(
            "Opening Inventory posting setup validation failed:\n - "
            .implode("\n - ", $errors),
        );
    }

    /**
     * @param  Collection<string, Item>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildOpeningInventoryLines(
        Collection $items,
    ): array {
        $lines = [];

        foreach (
            self::OPENING_QUANTITIES as $itemCode => $quantity
        ) {
            /** @var Item|null $item */
            $item = $items->get($itemCode);

            if ($item === null) {
                throw new RuntimeException(
                    "Opening Inventory item {$itemCode} was not found.",
                );
            }

            if (! $item->location_id) {
                throw new RuntimeException(
                    "Opening Inventory item {$itemCode} has no "
                    .'default Location.',
                );
            }

            if (! $item->base_uom_id) {
                throw new RuntimeException(
                    "Opening Inventory item {$itemCode} has no "
                    .'Base Unit of Measure.',
                );
            }

            if ($item->baseUom === null) {
                throw new RuntimeException(
                    "Opening Inventory item {$itemCode} has an "
                    .'invalid Base Unit of Measure relationship.',
                );
            }

            if (bccomp($quantity, '0', 8) <= 0) {
                throw new RuntimeException(
                    "Opening Inventory quantity for item {$itemCode} "
                    .'must be greater than zero.',
                );
            }

            $unitCost = (string) $item->unit_cost;

            if (bccomp($unitCost, '0', 8) <= 0) {
                throw new RuntimeException(
                    "Opening Inventory item {$itemCode} must have "
                    .'a positive unit cost.',
                );
            }

            $lines[] = [
                'item_id' => $item->id,
                'location_id' => $item->location_id,
                'unit_of_measure_id' => $item->base_uom_id,
                'unit_of_measure_code' => $item->baseUom->uom_code,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ];
        }

        return $lines;
    }
}

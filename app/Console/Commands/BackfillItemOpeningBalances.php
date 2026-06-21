<?php

namespace App\Console\Commands;

use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('inventory:backfill-opening-balances
    {--apply : Persist changes. Without this flag, the command runs as dry-run}
    {--item=* : Optional item codes to process}
    {--posting-date= : Posting date (Y-m-d). Defaults to today}
    {--force : Also backfill when open ledger quantity already exists}')]
#[Description('Backfill opening positive item ledger layers from item-card inventory balances')]
class BackfillItemOpeningBalances extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');
        $itemCodes = array_filter((array) $this->option('item'));
        $postingDate = $this->resolvePostingDate();
        $documentNumber = 'OPENING-BAL-'.now()->format('Ymd');

        $query = Item::query()
            ->when($itemCodes !== [], function (Builder $builder) use ($itemCodes): void {
                $builder->whereIn('item_code', $itemCodes);
            })
            ->where('inventory', '>', 0)
            ->orderBy('item_code');

        $items = $query->get();
        if ($items->isEmpty()) {
            $this->warn('No items matched the criteria.');

            return self::SUCCESS;
        }

        $this->info(($apply ? 'Apply' : 'Dry-run')." mode for {$items->count()} item(s).");

        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $openLedgerQty = (float) ItemLedgerEntry::query()
                ->where('item_id', $item->id)
                ->where('open', true)
                ->sum('remaining_quantity');

            $cardInventory = (float) $item->inventory;

            if (! $force && $openLedgerQty > 0) {
                $skipped++;
                $this->line("SKIP {$item->item_code}: open ledger qty exists ({$openLedgerQty}).");

                continue;
            }

            $qtyToBackfill = max(0.0, $cardInventory - max(0.0, $openLedgerQty));
            if ($qtyToBackfill <= 0) {
                $skipped++;
                $this->line("SKIP {$item->item_code}: nothing to backfill.");

                continue;
            }

            $costAmount = $qtyToBackfill * (float) ($item->unit_cost ?? 0);
            $message = sprintf(
                '%s %s: +%0.4f at unit_cost %0.4f (amount %0.4f)',
                $apply ? 'CREATE' : 'PLAN',
                $item->item_code,
                $qtyToBackfill,
                (float) ($item->unit_cost ?? 0),
                $costAmount
            );
            $this->line($message);

            if (! $apply) {
                $created++;

                continue;
            }

            DB::transaction(function () use ($item, $qtyToBackfill, $costAmount, $postingDate, $documentNumber): void {
                ItemLedgerEntry::create([
                    'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
                    'item_id' => $item->id,
                    'quantity' => $qtyToBackfill,
                    'remaining_quantity' => $qtyToBackfill,
                    'open' => true,
                    'posting_date' => $postingDate,
                    'document_number' => $documentNumber,
                    'document_line_number' => 10000,
                    'source_id' => null,
                    'source_type' => null,
                    'location_id' => $item->location_id,
                    'cost_amount_actual' => $costAmount,
                    'cost_amount_expected' => 0,
                    'purchase_amount_actual' => 0,
                    'dimensions' => null,
                    'general_business_posting_group_id' => null,
                    'general_product_posting_group_id' => $item->general_product_posting_group_id,
                    'inventory_posting_group_id' => $item->inventory_posting_group_id,
                    'entry_date' => now(),
                ]);
            });

            $created++;
        }

        $this->newLine();
        $this->info("Processed: {$items->count()}, Planned/Created: {$created}, Skipped: {$skipped}");
        if (! $apply) {
            $this->comment('Dry-run complete. Re-run with --apply to persist changes.');
        }

        return self::SUCCESS;
    }

    private function resolvePostingDate(): string
    {
        $option = $this->option('posting-date');
        if (! is_string($option) || trim($option) === '') {
            return now()->toDateString();
        }

        return CarbonImmutable::parse($option)->toDateString();
    }
}

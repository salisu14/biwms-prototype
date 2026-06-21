<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteArchive;
use App\Models\PurchaseQuoteLineArchive;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseQuoteVersioningService
{
    /**
     * Archive current version before modification (BC: Archive Document)
     */
    public function archive(PurchaseQuote $quote): PurchaseQuoteArchive
    {
        return DB::transaction(function () use ($quote) {
            $versionNo = $this->getNextVersionNo($quote);

            $archive = PurchaseQuoteArchive::create([
                'purchase_quote_id' => $quote->id,
                'version_no' => $versionNo,
                'document_no' => $quote->document_no,
                'status' => $quote->status->value,
                'vendor_id' => $quote->vendor_id,
                'amount' => $quote->amount,
                'amount_including_vat' => $quote->amount_including_vat,
                'archived_at' => now(),
                'archived_by' => Auth::id(),
                'quote_data' => $this->serializeQuote($quote),
            ]);

            foreach ($quote->lines as $line) {
                PurchaseQuoteLineArchive::create([
                    'purchase_quote_archive_id' => $archive->id,
                    'line_no' => $line->line_no,
                    'type' => $line->type->value,
                    'no' => $line->no,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'direct_unit_cost' => $line->direct_unit_cost,
                    'line_amount' => $line->line_amount,
                    'line_data' => $line->toArray(),
                ]);
            }

            return $archive;
        });
    }

    /**
     * Restore from archive (BC: Restore Document)
     */
    public function restore(PurchaseQuoteArchive $archive): PurchaseQuote
    {
        return DB::transaction(function () use ($archive) {
            $quote = $archive->purchaseQuote;

            // Archive current state first
            $this->archive($quote);

            // Restore from archive
            $quoteData = $archive->quote_data;
            $quote->update([
                'vendor_id' => $archive->vendor_id,
                'document_date' => $quoteData['document_date'],
                'order_date' => $quoteData['order_date'],
                'due_date' => $quoteData['due_date'],
                'currency_code' => $quoteData['currency_code'],
                'payment_terms_code' => $quoteData['payment_terms_code'],
                'location_code' => $quoteData['location_code'],
                'shortcut_dimension_1_code' => $quoteData['shortcut_dimension_1_code'],
                'shortcut_dimension_2_code' => $quoteData['shortcut_dimension_2_code'],
                'vendor_note' => $quoteData['vendor_note'],
                'internal_note' => $quoteData['internal_note'],
            ]);

            // Delete current lines
            $quote->lines()->delete();

            // Restore lines from archive
            foreach ($archive->lineArchives as $lineArchive) {
                $lineData = $lineArchive->line_data;
                $quote->lines()->create([
                    'line_no' => $lineArchive->line_no,
                    'type' => $lineData['type'],
                    'no' => $lineData['no'],
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'unit_of_measure_code' => $lineData['unit_of_measure_code'],
                    'direct_unit_cost' => $lineData['direct_unit_cost'],
                    'line_discount_percent' => $lineData['line_discount_percent'],
                    'vat_percent' => $lineData['vat_percent'],
                    'requested_receipt_date' => $lineData['requested_receipt_date'],
                    'location_code' => $lineData['location_code'],
                ]);
            }

            $quote->calculateTotals();

            return $quote->fresh();
        });
    }

    /**
     * Compare two versions (BC: Compare Versions)
     */
    public function compare(PurchaseQuoteArchive $version1, PurchaseQuoteArchive $version2): array
    {
        $diff = [];

        // Compare header fields
        $headerFields = [
            'vendor_id', 'document_date', 'due_date', 'currency_code',
            'payment_terms_code', 'amount', 'amount_including_vat',
        ];

        foreach ($headerFields as $field) {
            $val1 = $version1->quote_data[$field] ?? null;
            $val2 = $version2->quote_data[$field] ?? null;

            if ($val1 !== $val2) {
                $diff['header'][$field] = [
                    'old' => $val1,
                    'new' => $val2,
                ];
            }
        }

        // Compare lines
        $lines1 = collect($version1->lineArchives)->keyBy('line_no');
        $lines2 = collect($version2->lineArchives)->keyBy('line_no');

        $allLineNos = $lines1->keys()->merge($lines2->keys())->unique()->sort();

        foreach ($allLineNos as $lineNo) {
            $line1 = $lines1->get($lineNo);
            $line2 = $lines2->get($lineNo);

            if (! $line1) {
                $diff['lines'][$lineNo] = ['status' => 'added', 'data' => $line2->toArray()];
            } elseif (! $line2) {
                $diff['lines'][$lineNo] = ['status' => 'removed', 'data' => $line1->toArray()];
            } elseif ($this->linesDiffer($line1, $line2)) {
                $diff['lines'][$lineNo] = [
                    'status' => 'modified',
                    'changes' => $this->getLineChanges($line1, $line2),
                ];
            }
        }

        return $diff;
    }

    private function getNextVersionNo(PurchaseQuote $quote): int
    {
        return PurchaseQuoteArchive::where('purchase_quote_id', $quote->id)->max('version_no') + 1;
    }

    private function serializeQuote(PurchaseQuote $quote): array
    {
        return $quote->toArray();
    }

    private function linesDiffer($line1, $line2): bool
    {
        $compareFields = ['type', 'no', 'description', 'quantity', 'direct_unit_cost', 'line_discount_percent'];

        foreach ($compareFields as $field) {
            if (($line1->line_data[$field] ?? null) !== ($line2->line_data[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function getLineChanges($line1, $line2): array
    {
        $changes = [];
        $compareFields = ['type', 'no', 'description', 'quantity', 'direct_unit_cost', 'line_discount_percent', 'vat_percent'];

        foreach ($compareFields as $field) {
            $val1 = $line1->line_data[$field] ?? null;
            $val2 = $line2->line_data[$field] ?? null;

            if ($val1 !== $val2) {
                $changes[$field] = ['from' => $val1, 'to' => $val2];
            }
        }

        return $changes;
    }
}

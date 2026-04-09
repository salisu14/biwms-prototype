<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Enums\PurchaseQuoteStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteLine;
use App\Models\Vendor;
use App\Services\NumberSeriesService;
use Illuminate\Support\Facades\DB;

class PurchaseQuoteService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly VendorService $vendorService
    ) {}

    public function createQuote(array $data): PurchaseQuote
    {
        return DB::transaction(function () use ($data) {
            $data['document_no'] = $this->numberSeriesService->getNextNo('P-QUOTE');
            $data['status'] = PurchaseQuoteStatus::OPEN;

            if (! empty($data['vendor_id'])) {
                $vendor = Vendor::findOrFail($data['vendor_id']);
                $this->vendorService->validateForTransaction($vendor);
            }

            $quote = PurchaseQuote::create($data);

            if (! empty($data['lines'])) {
                foreach ($data['lines'] as $lineData) {
                    $quote->addLine($lineData); // ✅ delegate to model
                }
            }

            $quote->refresh();

            return $quote->load(['lines', 'vendor']);
        });
    }

    /**
     * Add line (delegates to model)
     */
    public function addLine(PurchaseQuote $quote, array $data): PurchaseQuoteLine
    {
        return DB::transaction(function () use ($quote, $data) {
            return $quote->addLine($data);
        });
    }

    /**
     * Update line and recalculate totals
     */
    public function updateLine(PurchaseQuote $quote, int $lineNo, array $data): PurchaseQuoteLine
    {
        return DB::transaction(function () use ($quote, $lineNo, $data) {
            return $quote->updateLine($lineNo, $data);
        });
    }

    /**
     * Delete a line and recalculate totals
     */
    public function deleteLine(PurchaseQuote $quote, int $lineNo): bool
    {
        return DB::transaction(function () use ($quote, $lineNo) {
            return $quote->deleteLine($lineNo);
        });
    }

    public function insertLine(PurchaseQuote $quote, int $afterLineNo, array $data): PurchaseQuoteLine
    {
        return DB::transaction(function () use ($quote, $afterLineNo, $data) {
            return $quote->insertLine($afterLineNo, $data);
        });
    }

    public function renumberLines(PurchaseQuote $quote): void
    {
        $quote->renumberLines();
    }

    public function convertToOrder(PurchaseQuote $quote): PurchaseOrder
    {
        if (! $quote->canConvertToOrder()) {
            throw new \InvalidArgumentException(
                'Quote must be released and have lines before converting'
            );
        }

        return DB::transaction(function () use ($quote) {
            if (! $quote->vendor_id && $quote->contact_id) {
                $vendor = $this->vendorService->createFromContact($quote->contact_id);
                $quote->update(['vendor_id' => $vendor->id]);
            }

            $vendor = Vendor::findOrFail($quote->vendor_id);
            $this->vendorService->validateForTransaction($vendor);

            $orderData = [
                'vendor_id' => $quote->vendor_id,
                'quote_no' => $quote->document_no,
                'document_date' => now(),
                'order_date' => $quote->order_date,
                'due_date' => $quote->due_date,
                'requested_receipt_date' => $quote->requested_receipt_date,
                'promised_receipt_date' => $quote->promised_receipt_date,
                'currency_code' => $quote->currency_code ?? $vendor->currency,
                'payment_terms_code' => $quote->payment_terms_code ?? $vendor->payment_terms_code,
                'payment_method_code' => $quote->payment_method_code,
                'location_code' => $quote->location_code,
                'shortcut_dimension_1_code' => $quote->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $quote->shortcut_dimension_2_code,
                'dimensions' => $quote->dimensions,
                'vendor_note' => $quote->vendor_note,
            ];

            $order = $this->purchaseOrderService
                ->createFromQuote($orderData, $quote->lines);

            $quote->update([
                'status' => PurchaseQuoteStatus::CONVERTED,
                'quote_no' => $order->document_no,
            ]);

            return $order;
        });
    }

    //    public function updateLine(int $lineNo, array $data): PurchaseQuoteLine
    //    {
    //        if (!$this->status->canEdit()) {
    //            throw new \InvalidArgumentException(
    //                "Cannot update lines in a {$this->status->label()} quote"
    //            );
    //        }
    //
    //        $line = $this->lines()->where('line_no', $lineNo)->firstOrFail();
    //
    //        $line->update($data);
    //        $this->calculateTotals();
    //
    //        return $line->fresh();
    //    }

    /**
     * Calculate and update quote totals from lines
     */
    public function calculateTotals(): void
    {
        $totals = $this->lines()->selectRaw('
            SUM(line_amount) as amount,
            SUM(vat_amount) as vat_amount,
            SUM(amount_including_vat) as amount_including_vat
        ')->first();

        $this->update([
            'amount' => $totals->amount ?? 0,
            'vat_amount' => $totals->vat_amount ?? 0,
            'amount_including_vat' => $totals->amount_including_vat ?? 0,
        ]);
    }

    /**
     * Check if quote can be converted to order
     */
    public function canConvertToOrder(): bool
    {
        return $this->status->canConvertToOrder() && $this->lines()->exists();
    }

    // ... other methods ...

    //    public function convertToOrder(PurchaseQuote $quote): PurchaseOrder
    //    {
    //        if (!$quote->canConvertToOrder()) {
    //            throw new \InvalidArgumentException('Quote must be released before converting to order');
    //        }
    //
    //        return DB::transaction(function () use ($quote) {
    //            // Create vendor if quote was created from contact without vendor
    //            if (!$quote->vendor_id && $quote->contact_id) {
    //                $vendor = $this->vendorService->createFromContact($quote->contact_id);
    //                $quote->update(['vendor_id' => $vendor->id]);
    //            }
    //
    //            // Validate vendor can transact
    //            $vendor = \App\Models\Vendor::findOrFail($quote->vendor_id);
    //            $this->vendorService->validateForTransaction($vendor);
    //
    //            $orderData = [
    //                'vendor_id' => $quote->vendor_id,
    //                'quote_no' => $quote->document_no,
    //                'document_date' => now(),
    //                'order_date' => $quote->order_date,
    //                'due_date' => $quote->due_date,
    //                'requested_receipt_date' => $quote->requested_receipt_date,
    //                'promised_receipt_date' => $quote->promised_receipt_date,
    //                'currency_code' => $quote->currency_code ?? $vendor->currency,
    //                'payment_terms_code' => $quote->payment_terms_code ?? $vendor->payment_terms_code,
    //                'payment_method_code' => $quote->payment_method_code,
    //                'location_code' => $quote->location_code,
    //                'shortcut_dimension_1_code' => $quote->shortcut_dimension_1_code,
    //                'shortcut_dimension_2_code' => $quote->shortcut_dimension_2_code,
    //                'dimensions' => $quote->dimensions,
    //                'vendor_note' => $quote->vendor_note,
    //            ];
    //
    //            $order = $this->purchaseOrderService->createFromQuote($orderData, $quote->lines);
    //
    //            $quote->update([
    //                'status' => PurchaseQuoteStatus::CONVERTED,
    //                'quote_no' => $order->document_no,
    //            ]);
    //
    //            return $order;
    //        });
    //    }
}
